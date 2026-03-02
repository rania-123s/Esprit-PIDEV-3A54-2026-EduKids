<?php

namespace App\Controller\Evenement;

use App\Entity\Evenement;
use App\Form\Evenement\EvenementType;
use App\Repository\Evenement\EvenementRepository;
use App\Service\EventNotificationService;
use App\Service\ImageRecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/evenement')]
class EvenementController extends AbstractController
{
    #[Route('/', name: 'app_evenement_index', methods: ['GET'])]
    public function index(EvenementRepository $evenementRepository): Response
    {
        return $this->render('evenement/index.html.twig', [
            'evenements' => $evenementRepository->findAll(),
        ]);
    }

    #[Route('/statistiques', name: 'app_evenement_statistiques', methods: ['GET'])]
    public function statistiques(EvenementRepository $evenementRepository): Response
    {
        $stats = $evenementRepository->getStats();

        return $this->render('evenement/statistiques.html.twig', [
            'totalEvents' => $stats['totalEvents'],
            'totalLikes' => $stats['totalLikes'],
            'totalDislikes' => $stats['totalDislikes'],
            'totalFavorites' => $stats['totalFavorites'],
            'mostLiked' => $evenementRepository->findTopByLikes(10),
            'mostDisliked' => $evenementRepository->findTopByDislikes(10),
            'mostFavorited' => $evenementRepository->findTopByFavorites(10),
        ]);
    }

    #[Route('/new', name: 'app_evenement_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        EventNotificationService $eventNotificationService
    ): Response {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement, [
            'is_new' => true,
            'current_image' => null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageUpload = $form->get('imageUpload')->getData();
            if ($imageUpload) {
                $originalFilename = pathinfo($imageUpload->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = 'event-' . $safeFilename . '-' . uniqid() . '.' . $imageUpload->guessExtension();

                try {
                    $imageUpload->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                    $evenement->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l upload de l image.');
                }
            }

            $entityManager->persist($evenement);
            $entityManager->flush();

            try {
                $report = $eventNotificationService->notifyNewEvent($evenement);

                if (($report['sent'] ?? 0) > 0) {
                    $this->addFlash('info', sprintf('%d email(s) de notification envoye(s).', (int) $report['sent']));
                }

                if (($report['failed'] ?? 0) > 0) {
                    $this->addFlash('warning', sprintf('%d email(s) de notification ont echoue.', (int) $report['failed']));
                }
            } catch (\Throwable $e) {
                $this->addFlash('warning', 'Evenement cree, mais echec de l envoi des emails de notification.');
            }

            $this->addFlash('success', 'L evenement a ete cree avec succes.');

            return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('evenement/new.html.twig', [
            'evenement' => $evenement,
            'form' => $form,
        ]);
    }

    #[Route('/recommend-image', name: 'app_evenement_recommend_image', methods: ['POST'])]
    public function recommendImage(
        Request $request,
        ImageRecommendationService $imageRecommendationService,
        LoggerInterface $logger
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $titre = $data['titre'] ?? '';
        $description = $data['description'] ?? '';

        if ($titre === '' && $description === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Le titre ou la description est requis.',
            ], 400);
        }

        try {
            $result = $imageRecommendationService->recommendImage($titre, $description);
            $keywords = is_array($result['keywords']) ? $result['keywords'] : [];

            $response = new JsonResponse([
                'success' => true,
                'imageUrl' => $result['imageUrl'] ?? null,
                'keywords' => $keywords,
                'searchUrl' => $result['searchUrl'] ?? null,
                'message' => 'Image recommandee generee avec succes.',
            ]);
            $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $response;
        } catch (\Exception $e) {
            $logger->error('Erreur lors de la generation des recommandations: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            $response = new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la generation des recommandations: ' . $e->getMessage(),
            ], 500);
            $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $response;
        }
    }

    #[Route('/{id}', name: 'app_evenement_show', methods: ['GET'])]
    public function show(Evenement $evenement): Response
    {
        return $this->render('evenement/show.html.twig', [
            'evenement' => $evenement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_evenement_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Evenement $evenement,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $form = $this->createForm(EvenementType::class, $evenement, [
            'is_new' => false,
            'current_image' => $evenement->getImage(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageUpload = $form->get('imageUpload')->getData();
            if ($imageUpload) {
                $originalFilename = pathinfo($imageUpload->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = 'event-' . $safeFilename . '-' . uniqid() . '.' . $imageUpload->guessExtension();

                try {
                    $imageUpload->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );

                    $oldImage = $evenement->getImage();
                    if ($oldImage) {
                        $oldImagePath = $this->getParameter('uploads_directory') . '/' . $oldImage;
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }

                    $evenement->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l upload de l image.');
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'L evenement a ete modifie avec succes.');

            return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('evenement/edit.html.twig', [
            'evenement' => $evenement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_evenement_delete', methods: ['POST'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $evenement->getId(), $request->getPayload()->getString('_token'))) {
            $image = $evenement->getImage();
            if ($image) {
                $imagePath = $this->getParameter('uploads_directory') . '/' . $image;
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $entityManager->remove($evenement);
            $entityManager->flush();
            $this->addFlash('success', 'L evenement a ete supprime avec succes.');
        }

        return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
    }
}
