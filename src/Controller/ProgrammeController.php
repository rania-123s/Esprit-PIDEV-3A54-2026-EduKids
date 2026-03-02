<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\Programme;
use App\Form\ProgrammeType;
use App\Repository\Evenement\EvenementRepository;
use App\Repository\ProgrammeRepository;
use App\Service\ActivityRecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/programme')]
class ProgrammeController extends AbstractController
{
    #[Route('/', name: 'app_programme_index', methods: ['GET'])]
    public function index(ProgrammeRepository $programmeRepository): Response
    {
        return $this->render('programme/index.html.twig', [
            'programmes' => $programmeRepository->findAllWithEvenements(),
        ]);
    }

    #[Route('/new', name: 'app_programme_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $programme = new Programme();
        $form = $this->createForm(ProgrammeType::class, $programme);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($programme);
            $entityManager->flush();

            $this->addFlash('success', 'Le programme a ete cree avec succes.');

            return $this->redirectToRoute('app_programme_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('programme/new.html.twig', [
            'programme' => $programme,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_programme_show', methods: ['GET'])]
    public function show(Programme $programme): Response
    {
        return $this->render('programme/show.html.twig', [
            'programme' => $programme,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_programme_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Programme $programme, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProgrammeType::class, $programme);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le programme a ete modifie avec succes.');

            return $this->redirectToRoute('app_programme_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('programme/edit.html.twig', [
            'programme' => $programme,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_programme_delete', methods: ['POST'])]
    public function delete(Request $request, Programme $programme, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $programme->getId(), $request->request->get('_token'))) {
            $entityManager->remove($programme);
            $entityManager->flush();

            $this->addFlash('success', 'Le programme a ete supprime avec succes.');
        }

        return $this->redirectToRoute('app_programme_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/api/evenement/{id}/heures', name: 'app_programme_api_evenement_heures', methods: ['GET'])]
    public function getEvenementHeures(Evenement $evenement): JsonResponse
    {
        if (!$this->hasValidEventHours($evenement)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Cet evenement n a pas d horaires valides. Modifiez heure debut/fin dans l evenement.',
            ], 400);
        }

        return new JsonResponse([
            'success' => true,
            'heureDebut' => $evenement->getHeureDebut()?->format('H:i'),
            'heureFin' => $evenement->getHeureFin()?->format('H:i'),
        ]);
    }

    #[Route('/api/recommend-activities', name: 'app_programme_api_recommend_activities', methods: ['POST'])]
    public function recommendActivities(
        Request $request,
        ActivityRecommendationService $activityRecommendationService,
        EvenementRepository $evenementRepository,
        LoggerInterface $logger
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $evenementId = $data['evenement_id'] ?? null;
        $pauseDebut = $data['pause_debut'] ?? null;
        $pauseFin = $data['pause_fin'] ?? null;

        if (!$evenementId) {
            return new JsonResponse([
                'success' => false,
                'message' => 'L ID de l evenement est requis.',
            ], 400);
        }

        $evenement = $evenementRepository->find($evenementId);
        if (!$evenement) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Evenement non trouve.',
            ], 404);
        }

        if (!$this->hasValidEventHours($evenement)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'L evenement doit avoir un intervalle horaire valide (debut < fin).',
            ], 400);
        }

        try {
            $result = $activityRecommendationService->recommendActivities(
                (string) $evenement->getTitre(),
                (string) $evenement->getDescription(),
                (string) $evenement->getHeureDebut()?->format('H:i'),
                (string) $evenement->getHeureFin()?->format('H:i'),
                $pauseDebut,
                $pauseFin
            );

            if (!$result['success']) {
                if (
                    strpos($result['message'], 'Cle API Gemini') !== false
                    || strpos($result['message'], 'Impossible de generer') !== false
                ) {
                    $result['message'] .= ' Consultez DEBUG_GEMINI_API.md pour le diagnostic.';
                }
            }

            $response = new JsonResponse($result);
            $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $response;
        } catch (\Exception $e) {
            $logger->error('Erreur dans ProgrammeController::recommendActivities: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'success' => false,
                'activites' => null,
                'message' => 'Erreur serveur lors de la generation des activites. Consultez var/log/dev.log.',
            ], 500);
        }
    }

    private function hasValidEventHours(Evenement $evenement): bool
    {
        $heureDebut = $evenement->getHeureDebut();
        $heureFin = $evenement->getHeureFin();

        if (!$heureDebut || !$heureFin) {
            return false;
        }

        $debutMinutes = ((int) $heureDebut->format('H') * 60) + (int) $heureDebut->format('i');
        $finMinutes = ((int) $heureFin->format('H') * 60) + (int) $heureFin->format('i');

        return $debutMinutes < $finMinutes;
    }
}
