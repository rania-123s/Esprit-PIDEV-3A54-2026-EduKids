<?php

namespace App\Controller\Quiz;

use App\Entity\Quiz\Question;
use App\Entity\Quiz\QuestionOption;
use App\Entity\Quiz\Quiz;
use App\Form\Quiz\QuestionType;
use App\Form\Quiz\QuizType;
use App\Repository\Quiz\QuestionRepository;
use App\Repository\Quiz\QuizAttemptRepository;
use App\Repository\Quiz\QuizRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/admin/quiz')]
#[IsGranted('ROLE_ADMIN')]
class QuizAdminController extends AbstractController
{
    private const POLLINATIONS_IMAGE_BASE = 'https://gen.pollinations.ai/image';

    public function __construct(
        private QuizRepository $quizRepository,
        private QuestionRepository $questionRepository,
        private QuizAttemptRepository $attemptRepository,
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private ?string $pollinationsApiKey = null,
        private ?string $openaiApiKey = null,
    ) {
        $this->pollinationsApiKey = $pollinationsApiKey ?? '';
        $this->openaiApiKey = $openaiApiKey ?? '';
    }

    #[Route('', name: 'quiz_admin_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('back_office/quiz/index.html.twig');
    }

    #[Route('/list', name: 'quiz_admin_quiz_list', methods: ['GET'])]
    public function quizList(Request $request): Response
    {
        $q = $request->query->get('q');
        $published = $request->query->get('published');
        $publishedFilter = null;
        if ($published === '1' || $published === 'true') {
            $publishedFilter = true;
        } elseif ($published === '0' || $published === 'false') {
            $publishedFilter = false;
        }
        $sort = $request->query->get('sort', 'titre');
        $order = $request->query->get('order', 'ASC');
        $quizzes = $this->quizRepository->searchAndSort($q, $publishedFilter, $sort, $order);

        return $this->render('back_office/quiz/quiz_list.html.twig', [
            'quizzes' => $quizzes,
            'searchQuery' => $q,
            'filterPublished' => $publishedFilter,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    /**
     * Affiche le quiz comme pour l'étudiant (page d'accueil du quiz).
     */
    #[Route('/{id}/preview', name: 'quiz_admin_quiz_preview', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function preview(Quiz $quiz): Response
    {
        $questions = $this->questionRepository->findByQuizOrdered($quiz);
        return $this->render('quiz/show.html.twig', [
            'quiz' => $quiz,
            'questions' => $questions,
            'isAdminPreview' => true,
            'previewPlayPath' => $this->generateUrl('quiz_admin_quiz_preview_play', ['id' => $quiz->getId()]),
            'backPath' => $this->generateUrl('quiz_admin_quiz_list'),
        ]);
    }

    /**
     * Affiche le quiz en mode jeu comme pour l'étudiant (questions).
     */
    #[Route('/{id}/preview/play', name: 'quiz_admin_quiz_preview_play', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function previewPlay(Quiz $quiz): Response
    {
        $questions = $this->questionRepository->findByQuizOrdered($quiz);
        return $this->render('quiz/play.html.twig', [
            'quiz' => $quiz,
            'questions' => $questions,
            'isAdminPreview' => true,
            'backPath' => $this->generateUrl('quiz_admin_quiz_list'),
        ]);
    }

    #[Route('/generate-image-url', name: 'quiz_admin_generate_image_url', methods: ['POST'])]
    public function generateImageUrl(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('quiz_generate_image', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token invalide.'], 403);
        }
        if ($this->pollinationsApiKey === '') {
            return new JsonResponse(['error' => 'POLLINATIONS_API_KEY non configurée.'], 503);
        }
        $prompt = trim((string) ($request->request->get('prompt') ?? $request->request->get('titre') ?? ''));
        if ($prompt === '') {
            return new JsonResponse(['error' => 'Indiquez un titre (prompt).'], 400);
        }
        $proxyUrl = $this->generateUrl('quiz_admin_generated_image', ['prompt' => $prompt], true);
        return new JsonResponse(['url' => $proxyUrl]);
    }

    #[Route('/generated-image', name: 'quiz_admin_generated_image', methods: ['GET'])]
    public function generatedImage(Request $request): Response
    {
        if ($this->pollinationsApiKey === '') {
            return new Response('Image generation not configured.', 503);
        }
        $prompt = $request->query->get('prompt', '');
        $prompt = trim((string) $prompt);
        if ($prompt === '') {
            return new Response('Missing prompt.', 400);
        }
        $imagePrompt = $prompt . ', educational quiz, clean illustration, professional, kids friendly';
        $encodedPrompt = rawurlencode($imagePrompt);
        $url = self::POLLINATIONS_IMAGE_BASE . '/' . $encodedPrompt . '?model=flux';
        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->pollinationsApiKey,
            ],
            'timeout' => 60,
        ]);
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            return new Response('Image generation failed.', $statusCode);
        }
        $headers = $response->getHeaders(false);
        $contentType = $headers['content-type'][0] ?? 'image/png';
        return new StreamedResponse(function () use ($response): void {
            foreach ($this->httpClient->stream($response) as $chunk) {
                echo $chunk->getContent();
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }
        }, 200, ['Content-Type' => $contentType]);
    }

    #[Route('/generate-questions', name: 'quiz_admin_generate_questions', methods: ['POST'])]
    public function generateQuestions(Request $request): JsonResponse
    {
        try {
            if (!$this->isCsrfTokenValid('quiz_generate_questions', $request->request->get('_token'))) {
                return new JsonResponse(['error' => 'Token invalide.', 'questions' => []], 403);
            }
            $titre = trim((string) ($request->request->get('titre') ?? ''));
            $count = (int) ($request->request->get('count') ?? 3);
            $count = max(1, min(10, $count));
            if ($titre === '') {
                return new JsonResponse(['error' => 'Le titre du quiz est requis.', 'questions' => []], 400);
            }

            $questions = $this->generateQuestionsWithAi($titre, $count);
            return new JsonResponse(['questions' => $questions]);
        } catch (\Throwable $e) {
            $titre = trim((string) ($request->request->get('titre') ?? 'Quiz'));
            $count = max(1, min(10, (int) ($request->request->get('count') ?? 3)));
            $fallback = [];
            for ($i = 0; $i < $count; $i++) {
                $fallback[] = [
                    'texte' => sprintf('Question %d : Quelle est la bonne réponse concernant « %s » ?', $i + 1, $titre),
                    'type' => 'qcm',
                    'options' => ['Réponse A', 'Réponse B', 'Réponse C', 'Réponse D'],
                    'bonneReponse' => '0',
                    'ordre' => $i,
                ];
            }
            return new JsonResponse(['questions' => $fallback, 'error' => 'Génération IA indisponible, suggestions par défaut affichées.']);
        }
    }

    private function generateQuestionsWithAi(string $titre, int $count): array
    {
        if ($this->openaiApiKey !== '' && $this->openaiApiKey !== '%env(OPENAI_API_KEY)%') {
            try {
                $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->openaiApiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'gpt-4o-mini',
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'Tu es un professeur. Génère des questions de quiz au format JSON strict. Réponds UNIQUEMENT par un tableau JSON, sans markdown. Chaque élément: {"texte": "énoncé", "type": "qcm", "options": ["A", "B", "C", "D"], "bonneReponse": "0"} (bonneReponse = indice 0-based) ou {"texte": "énoncé", "type": "texte", "options": null, "bonneReponse": "réponse attendue"}.',
                            ],
                            [
                                'role' => 'user',
                                'content' => sprintf('Génère %d questions de quiz sur le thème : "%s". Réponds uniquement par le tableau JSON.', $count, $titre),
                            ],
                        ],
                        'temperature' => 0.7,
                    ],
                    'timeout' => 30,
                ]);
                if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                    $data = $response->toArray();
                    $content = $data['choices'][0]['message']['content'] ?? '';
                    $content = preg_replace('/^```\w*\n?|\n?```$/', '', trim($content));
                    $decoded = json_decode($content, true);
                    if (\is_array($decoded)) {
                        $out = [];
                        foreach ($decoded as $i => $q) {
                            if (isset($q['texte'])) {
                                $out[] = [
                                    'texte' => $q['texte'],
                                    'type' => $q['type'] ?? 'qcm',
                                    'options' => $q['options'] ?? [],
                                    'bonneReponse' => (string) ($q['bonneReponse'] ?? '0'),
                                    'ordre' => $i,
                                ];
                            }
                        }
                        if (!empty($out)) {
                            return $out;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // fallback to template
            }
        }

        // Fallback: template-based generation (same style as ecommerce description)
        $out = [];
        $themes = ['définition', 'calcul', 'application', 'raisonnement'];
        for ($i = 0; $i < $count; $i++) {
            $theme = $themes[$i % \count($themes)];
            $out[] = [
                'texte' => sprintf('Question %d sur le thème "%s" : Quelle est la bonne réponse concernant "%s" ?', $i + 1, $theme, $titre),
                'type' => 'qcm',
                'options' => ['Réponse A', 'Réponse B', 'Réponse C', 'Réponse D'],
                'bonneReponse' => '0',
                'ordre' => $i,
            ];
        }
        return $out;
    }

    #[Route('/new', name: 'quiz_admin_quiz_new', methods: ['GET', 'POST'])]
    public function quizNew(Request $request): Response
    {
        $quiz = new Quiz();
        $form = $this->createForm(QuizType::class, $quiz);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $quiz->setUpdatedAt(new \DateTimeImmutable());
            $this->quizRepository->save($quiz, true);
            $this->addFlash('success', 'Quiz créé.');
            return $this->redirectToRoute('quiz_admin_question_list', ['id' => $quiz->getId()]);
        }

        return $this->render('back_office/quiz/quiz_form.html.twig', [
            'quiz' => $quiz,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'quiz_admin_quiz_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function quizEdit(Quiz $quiz, Request $request): Response
    {
        $form = $this->createForm(QuizType::class, $quiz);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $quiz->setUpdatedAt(new \DateTimeImmutable());
            $this->quizRepository->save($quiz, true);
            $this->addFlash('success', 'Quiz mis à jour.');
            return $this->redirectToRoute('quiz_admin_quiz_list');
        }

        return $this->render('back_office/quiz/quiz_form.html.twig', [
            'quiz' => $quiz,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'quiz_admin_quiz_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function quizDelete(Quiz $quiz, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_quiz' . $quiz->getId(), $request->request->get('_token'))) {
            $this->quizRepository->remove($quiz, true);
            $this->addFlash('success', 'Quiz supprimé.');
        }
        return $this->redirectToRoute('quiz_admin_quiz_list');
    }

    /**
     * Save quiz (e.g. after adding all questions). Updates updatedAt and redirects to question list.
     */
    #[Route('/{id}/save', name: 'quiz_admin_quiz_save', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function quizSave(Quiz $quiz, Request $request): Response
    {
        if ($this->isCsrfTokenValid('save_quiz' . $quiz->getId(), $request->request->get('_token'))) {
            $quiz->setUpdatedAt(new \DateTimeImmutable());
            $this->quizRepository->save($quiz, true);
            $this->addFlash('success', 'Quiz enregistré avec succès.');
        }
        return $this->redirectToRoute('quiz_admin_question_list', ['id' => $quiz->getId()]);
    }

    #[Route('/{id}/questions', name: 'quiz_admin_question_list', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function questionList(Quiz $quiz): Response
    {
        $questions = $this->questionRepository->findByQuizOrdered($quiz);
        return $this->render('back_office/quiz/question_list.html.twig', [
            'quiz' => $quiz,
            'questions' => $questions,
        ]);
    }

    #[Route('/{id}/questions/new', name: 'quiz_admin_question_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function questionNew(Quiz $quiz, Request $request): Response
    {
        $question = new Question();
        $question->setQuiz($quiz);
        $maxOrdre = 0;
        foreach ($quiz->getQuestions() as $q) {
            if ($q->getOrdre() >= $maxOrdre) {
                $maxOrdre = $q->getOrdre() + 1;
            }
        }
        $question->setOrdre($maxOrdre);

        // Pour une nouvelle question (QCM), afficher 2 champs d'option vides par défaut
        $question->addQuestionOption((new QuestionOption())->setOrdre(0)->setCorrect(false));
        $question->addQuestionOption((new QuestionOption())->setOrdre(1)->setCorrect(false));

        $form = $this->createForm(QuestionType::class, $question, ['ordre_default' => $maxOrdre]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->questionRepository->save($question, true);
            $this->addFlash('success', 'Question ajoutée.');
            return $this->redirectToRoute('quiz_admin_question_list', ['id' => $quiz->getId()]);
        }

        return $this->render('back_office/quiz/question_form.html.twig', [
            'quiz' => $quiz,
            'question' => $question,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/questions/{questionId}/edit', name: 'quiz_admin_question_edit', requirements: ['id' => '\d+', 'questionId' => '\d+'], methods: ['GET', 'POST'])]
    public function questionEdit(Quiz $quiz, int $questionId, Request $request): Response
    {
        $question = $this->questionRepository->findOneBy(['id' => $questionId, 'quiz' => $quiz]);
        if (!$question) {
            throw $this->createNotFoundException('Question non trouvée.');
        }

        $form = $this->createForm(QuestionType::class, $question, ['ordre_default' => $question->getOrdre()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->questionRepository->save($question, true);
            $this->addFlash('success', 'Question mise à jour.');
            return $this->redirectToRoute('quiz_admin_question_list', ['id' => $quiz->getId()]);
        }

        return $this->render('back_office/quiz/question_form.html.twig', [
            'quiz' => $quiz,
            'question' => $question,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/questions/{questionId}/delete', name: 'quiz_admin_question_delete', requirements: ['id' => '\d+', 'questionId' => '\d+'], methods: ['POST'])]
    public function questionDelete(Quiz $quiz, int $questionId, Request $request): Response
    {
        $question = $this->questionRepository->findOneBy(['id' => $questionId, 'quiz' => $quiz]);
        if ($question && $this->isCsrfTokenValid('delete_question' . $questionId, $request->request->get('_token'))) {
            $this->questionRepository->remove($question, true);
            $this->addFlash('success', 'Question supprimée.');
        }
        return $this->redirectToRoute('quiz_admin_question_list', ['id' => $quiz->getId()]);
    }

    #[Route('/statistics', name: 'quiz_admin_statistics', methods: ['GET'])]
    public function statistics(): Response
    {
        $totalAttempts = $this->attemptRepository->countAll();
        $quizzes = $this->quizRepository->searchAndSort(null, null, 'titre', 'ASC');
        $quizStats = [];
        foreach ($quizzes as $quiz) {
            $attemptCount = $this->attemptRepository->countByQuiz($quiz);
            $avgScore = $this->attemptRepository->getAverageScoreForQuiz($quiz);
            $quizStats[] = [
                'quiz' => $quiz,
                'attemptCount' => $attemptCount,
                'averageScore' => $avgScore,
            ];
        }
        usort($quizStats, fn ($a, $b) => $b['attemptCount'] <=> $a['attemptCount']);
        $chart = $this->attemptRepository->getAttemptsByPeriod('day', 30);

        return $this->render('back_office/quiz/statistics.html.twig', [
            'totalAttempts' => $totalAttempts,
            'quizStats' => $quizStats,
            'chartLabels' => $chart['labels'],
            'chartValues' => $chart['values'],
        ]);
    }

    #[Route('/export', name: 'quiz_admin_export', methods: ['GET'])]
    public function export(Request $request): StreamedResponse|Response
    {
        $format = $request->query->get('format');
        if ($format === 'csv') {
            $quizzes = $this->quizRepository->searchAndSort(null, null, 'titre', 'ASC');
            $response = new StreamedResponse(function () use ($quizzes) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['ID Quiz', 'Titre', 'Publié', 'Nombre questions', 'Tentatives', 'Score moyen %'], ';');
                foreach ($quizzes as $quiz) {
                    $attemptCount = $this->attemptRepository->countByQuiz($quiz);
                    $avgScore = $this->attemptRepository->getAverageScoreForQuiz($quiz);
                    fputcsv($out, [
                        $quiz->getId(),
                        $quiz->getTitre(),
                        $quiz->isPublished() ? 'Oui' : 'Non',
                        $quiz->getQuestions()->count(),
                        $attemptCount,
                        number_format($avgScore, 1, ',', ''),
                    ], ';');
                }
                fclose($out);
            });
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'quiz_export_' . date('Y-m-d') . '.csv'
            ));
            return $response;
        }

        return $this->redirectToRoute('quiz_admin_statistics');
    }
}
