<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\EvenementRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservation')]
class ReservationController extends AbstractController
{
    #[Route('/participer/{id}', name: 'app_reservation_participer', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function participer(
        Evenement $evenement,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Vérifier si des places sont disponibles
        if (!$evenement->hasPlacesDisponibles()) {
            $this->addFlash('error', 'Désolé, il n\'y a plus de places disponibles pour cet événement.');
            return $this->redirectToRoute('app_front_evenement_index');
        }

        $reservation = new Reservation();
        $reservation->setUser($user);
        $reservation->setEvenement($evenement);
        
        // Pré-remplir les champs avec les données de l'utilisateur
        $reservation->setNom($user->getLastName() ?? '');
        $reservation->setPrenom($user->getFirstName() ?? '');
        $reservation->setEmail($user->getEmail() ?? '');

        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier à nouveau les places disponibles AVANT de sauvegarder
            $nbPlacesDemandees = $reservation->getNbAdultes() + $reservation->getNbEnfants();
            
            // Vérifier que le nombre de places demandées est valide
            if ($nbPlacesDemandees <= 0) {
                $this->addFlash('error', 'Vous devez réserver au moins une place.');
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Vous devez réserver au moins une place.'
                    ], 400);
                }
                return $this->render('reservation/participer.html.twig', [
                    'evenement' => $evenement,
                    'form' => $form,
                ]);
            }
            
            // Vérifier les places disponibles si une limite est définie
            if ($evenement->getNbPlacesDisponibles() !== null) {
                // Recharger l'événement pour avoir les dernières réservations (sans la réservation en cours)
                $entityManager->refresh($evenement);
                
                // Calculer les places réservées SANS la réservation actuelle (car elle n'est pas encore sauvegardée)
                $nbPlacesReservees = $evenement->getNbPlacesReservees();
                $nbPlacesRestantes = $evenement->getNbPlacesDisponibles() - $nbPlacesReservees;
                
                if ($nbPlacesRestantes <= 0) {
                    $this->addFlash('error', 'Désolé, il n\'y a plus de places disponibles pour cet événement.');
                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Désolé, il n\'y a plus de places disponibles pour cet événement.'
                        ], 400);
                    }
                    return $this->render('reservation/participer.html.twig', [
                        'evenement' => $evenement,
                        'form' => $form,
                    ]);
                }
                
                // Vérifier strictement que le nombre de places demandées ne dépasse pas les places restantes
                if ($nbPlacesDemandees > $nbPlacesRestantes) {
                    $this->addFlash('error', 'Il ne reste que ' . $nbPlacesRestantes . ' place(s) disponible(s). Vous avez demandé ' . $nbPlacesDemandees . ' place(s). Veuillez réduire le nombre de places.');
                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Il ne reste que ' . $nbPlacesRestantes . ' place(s) disponible(s). Vous avez demandé ' . $nbPlacesDemandees . ' place(s). Veuillez réduire le nombre de places.',
                            'placesRestantes' => $nbPlacesRestantes
                        ], 400);
                    }
                    return $this->render('reservation/participer.html.twig', [
                        'evenement' => $evenement,
                        'form' => $form,
                    ]);
                }
            }

            // Sauvegarder la réservation
            $entityManager->persist($reservation);
            $entityManager->flush();

            // Recharger l'événement pour avoir le nombre de places réservées à jour
            $entityManager->refresh($evenement);
            $entityManager->refresh($reservation);

            $this->addFlash('success', 'Votre réservation de ' . $nbPlacesDemandees . ' place(s) a été enregistrée avec succès !');
            
            if ($request->isXmlHttpRequest()) {
                // Retourner l'ID de la réservation pour rediriger vers le pass
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Votre réservation de ' . $nbPlacesDemandees . ' place(s) a été enregistrée avec succès !',
                    'placesRestantes' => $evenement->getNbPlacesDisponibles() !== null 
                        ? ($evenement->getNbPlacesDisponibles() - $evenement->getNbPlacesReservees())
                        : null,
                    'reservationId' => $reservation->getId(),
                    'passUrl' => $this->generateUrl('app_reservation_pass', ['id' => $reservation->getId()])
                ]);
            }
            
            // Rediriger vers le pass après réservation
            return $this->redirectToRoute('app_reservation_pass', ['id' => $reservation->getId()]);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('reservation/_form_modal.html.twig', [
                'evenement' => $evenement,
                'form' => $form,
            ]);
        }

        return $this->render('reservation/participer.html.twig', [
            'evenement' => $evenement,
            'form' => $form,
        ]);
    }

    #[Route('/pass/{id}', name: 'app_reservation_pass', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function pass(
        Reservation $reservation,
        Request $request
    ): Response {
        $user = $this->getUser();
        
        // Vérifier que l'utilisateur est propriétaire de la réservation ou admin
        if ($reservation->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce pass.');
        }

        $evenement = $reservation->getEvenement();

        // Générer le pass en format imprimable
        return $this->render('reservation/pass.html.twig', [
            'reservation' => $reservation,
            'evenement' => $evenement,
        ]);
    }

    #[Route('/pass/{id}/pdf', name: 'app_reservation_pass_pdf', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function passPdf(
        Reservation $reservation,
        Request $request
    ): Response {
        $user = $this->getUser();
        
        // Vérifier que l'utilisateur est propriétaire de la réservation ou admin
        if ($reservation->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce pass.');
        }

        $evenement = $reservation->getEvenement();

        // Générer le HTML pour le PDF avec auto-impression
        $html = $this->renderView('reservation/pass_pdf.html.twig', [
            'reservation' => $reservation,
            'evenement' => $evenement,
        ]);

        // Retourner le HTML avec auto-impression en PDF
        $response = new Response($html);
        $response->headers->set('Content-Type', 'text/html; charset=utf-8');
        
        return $response;
    }
}
