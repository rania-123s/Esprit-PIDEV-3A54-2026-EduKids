<?php

namespace App\Controller;

use App\Entity\Cours;
use App\Form\CoursType;
use App\Repository\CoursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cours')]
final class CoursController extends AbstractController
{
    // ================= LIST =================
    #[Route('/', name: 'app_cours_index')]
    public function index(CoursRepository $coursRepository): Response
    {
        return $this->render('cours/index.html.twig', [
            'cours' => $coursRepository->findAll(),
        ]);
    }

    // ================= CREATE =================
    #[Route('/new', name: 'app_cours_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $cours = new Cours();
        $form = $this->createForm(CoursType::class, $cours);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($cours);
            $em->flush();

            $this->addFlash('success', 'Cours ajouté avec succès !');
            return $this->redirectToRoute('app_cours_index');
        }

        return $this->render('cours/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // ================= SHOW =================
    #[Route('/{id}', name: 'app_cours_show', requirements: ['id' => '\d+'])]
    public function show(Cours $cours): Response
    {
        return $this->render('cours/show.html.twig', [
            'cours' => $cours,
        ]);
    }

    // ================= EDIT =================
    #[Route('/{id}/edit', name: 'app_cours_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Cours $cours, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CoursType::class, $cours);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Cours modifié avec succès !');
            return $this->redirectToRoute('app_cours_index');
        }

        return $this->render('cours/edit.html.twig', [
            'form' => $form->createView(),
            'cours' => $cours,
        ]);
    }

    // ================= DELETE =================
    #[Route('/{id}/delete', name: 'app_cours_delete', methods: ['POST'])]
    public function delete(Request $request, Cours $cours, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$cours->getId(), $request->request->get('_token'))) {
            $em->remove($cours);
            $em->flush();
            $this->addFlash('success', 'Cours supprimé avec succès !');
        }

        return $this->redirectToRoute('app_cours_index');
    }
}


namespace App\Controller;

use App\Entity\Cours;
use App\Form\CoursType;
use App\Repository\CoursRepository;
use App\Service\CourseSmsNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/cours')]
final class CoursController extends AbstractController
{
    // ================= LIST =================
    #[Route('/', name: 'app_cours_index')]
    public function index(CoursRepository $coursRepository, Request $request, PaginatorInterface $paginator): Response
    {
        // Backward compatibility: old URLs used ?sort=...
        // KnpPaginator also uses "sort", which can trigger Doctrine errors with values like "a-z".
        if ($request->query->has('sort') && !$request->query->has('order')) {
            $params = $request->query->all();
            $params['order'] = $params['sort'];
            unset($params['sort']);

            return $this->redirectToRoute('app_cours_index', $params);
        }

        $searchQuery = trim((string) $request->query->get('search', ''));
        $sort = $request->query->get('order');

        if ($searchQuery) {
            $queryBuilder = $coursRepository->searchCours($searchQuery, $sort);
        } else {
            $queryBuilder = $coursRepository->findAllSorted($sort);
        }

        $cours = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            3
        );

        return $this->render('cours/index.html.twig', [
            'cours' => $cours,
            'searchQuery' => $searchQuery,
            'sort' => $sort,
        ]);
    }

    // ================= CREATE =================
    #[Route('/new', name: 'app_cours_new')]
    public function new(Request $request, EntityManagerInterface $em, CourseSmsNotifier $courseSmsNotifier): Response
    {
        $cours = new Cours();
        $form = $this->createForm(CoursType::class, $cours, [
            'image_required' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('image')->getData();
            $aiGeneratedImage = $this->sanitizeGeneratedImageFilename(
                (string) $request->request->get('ai_generated_image', '')
            );

            if ($file) {
                $fileName = md5(uniqid()) . '.' . $file->guessExtension();
                try {
                    $file->move(
                        $this->getParameter('images_directory') . '/cours',
                        $fileName
                    );
                    $cours->setImage($fileName);
                } catch (FileException $e) {
                    $form->get('image')->addError(new FormError('Erreur lors du telechargement de l image'));
                }
            } elseif ($aiGeneratedImage !== null && $this->isCourseImageAvailable($aiGeneratedImage)) {
                $cours->setImage($aiGeneratedImage);
            } else {
                $form->get('image')->addError(new FormError('L image du cours est obligatoire'));
            }

            if ($form->get('image')->getErrors(true)->count() > 0) {
                return $this->render('cours/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            // Final backend guard to avoid inserting a Cours with a null image.
            if (empty($cours->getImage())) {
                $form->get('image')->addError(new FormError('L image du cours est obligatoire'));

                return $this->render('cours/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $em->persist($cours);
            $em->flush();

            $smsReport = $courseSmsNotifier->notifyStudentsAboutNewCourse($cours);
            if (!$smsReport['configured']) {
                if ($smsReport['reason'] === 'missing_twilio_credentials') {
                    $this->addFlash('warning', 'SMS non envoye: configurez TWILIO_ACCOUNT_SID et TWILIO_AUTH_TOKEN.');
                } elseif ($smsReport['reason'] === 'invalid_twilio_from_number') {
                    $this->addFlash('warning', 'SMS non envoye: TWILIO_FROM_NUMBER invalide. Format attendu: +123456789.');
                } elseif ($smsReport['reason'] === 'missing_recipients') {
                    $this->addFlash('warning', 'SMS non envoye: ajoutez au moins un numero dans STUDENT_SMS_RECIPIENTS.');
                }
            } else {
                if ($smsReport['sent'] > 0) {
                    if ($smsReport['sent'] === 1) {
                        $this->addFlash('info', 'SMS envoye sur votre numero.');
                    } else {
                        $this->addFlash('info', sprintf('SMS envoye a %d numero(s).', $smsReport['sent']));
                    }
                }
                if ($smsReport['failed'] > 0) {
                    $this->addFlash('warning', sprintf('%d SMS n ont pas pu etre envoyes.', $smsReport['failed']));
                }
            }

            $this->addFlash('success', 'Cours ajoute avec succes !');

            return $this->redirectToRoute('app_cours_index');
        }

        return $this->render('cours/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/ai/generate', name: 'app_cours_ai_generate', methods: ['POST'])]
    public function generateWithAi(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json([
                'error' => 'Corps de requete invalide.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $csrfToken = trim((string) ($payload['_token'] ?? ''));
        if (!$this->isCsrfTokenValid('cours_ai_generate', $csrfToken)) {
            return $this->json([
                'error' => 'Jeton CSRF invalide.',
            ], Response::HTTP_FORBIDDEN);
        }

        $title = trim((string) ($payload['title'] ?? ''));
        $subject = trim((string) ($payload['subject'] ?? ''));
        $inputLevel = $this->normalizeSuggestedLevel($payload['level'] ?? null);

        if ($title === '' && $subject === '') {
            return $this->json([
                'error' => 'Donnez au moins un titre ou une matiere.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $draft = null;
        $source = 'local';

        $geminiApiKey = trim((string) ($_SERVER['GEMINI_API_KEY'] ?? $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY')));
        if ($geminiApiKey === '') {
            $geminiApiKey = trim((string) ($_SERVER['GOOGLE_GEMINI_API_KEY'] ?? $_ENV['GOOGLE_GEMINI_API_KEY'] ?? getenv('GOOGLE_GEMINI_API_KEY')));
        }
        $geminiModel = trim((string) ($_SERVER['GEMINI_MODEL'] ?? $_ENV['GEMINI_MODEL'] ?? getenv('GEMINI_MODEL')));
        if ($geminiModel === '') {
            $geminiModel = 'gemini-2.5-flash';
        }

        if ($geminiApiKey !== '') {
            $draft = $this->generateDraftWithGemini($httpClient, $geminiApiKey, $geminiModel, $title, $subject, $inputLevel);
            $source = $draft !== null ? 'ai' : 'local';
        }

        $openAiApiKey = trim((string) ($_SERVER['OPENAI_API_KEY'] ?? $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY')));
        $openAiModel = trim((string) ($_SERVER['OPENAI_MODEL'] ?? $_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL')));
        if ($openAiModel === '') {
            $openAiModel = 'gpt-4o-mini';
        }

        if ($draft === null && $openAiApiKey !== '') {
            $draft = $this->generateDraftWithOpenAi($httpClient, $openAiApiKey, $openAiModel, $title, $subject, $inputLevel);
            $source = $draft !== null ? 'ai' : 'local';
        }

        if ($draft === null) {
            $draft = $this->generateDraftLocally($title, $subject, $inputLevel);
        }

        $structuredDescription = $this->buildStructuredDescription(
            (string) ($draft['description'] ?? ''),
            (array) ($draft['objectives'] ?? []),
            (array) ($draft['plan'] ?? [])
        );

        return $this->json([
            'description' => (string) ($draft['description'] ?? ''),
            'objectives' => array_values((array) ($draft['objectives'] ?? [])),
            'plan' => array_values((array) ($draft['plan'] ?? [])),
            'suggested_level' => $this->normalizeSuggestedLevel($draft['suggested_level'] ?? null),
            'structured_description' => $structuredDescription,
            'source' => $source,
        ]);
    }

    #[Route('/ai/generate-image', name: 'app_cours_ai_generate_image', methods: ['POST'])]
    public function generateImageWithAi(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json([
                'error' => 'Corps de requete invalide.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $csrfToken = trim((string) ($payload['_token'] ?? ''));
        if (!$this->isCsrfTokenValid('cours_ai_generate_image', $csrfToken)) {
            return $this->json([
                'error' => 'Jeton CSRF invalide.',
            ], Response::HTTP_FORBIDDEN);
        }

        $title = trim((string) ($payload['title'] ?? ''));
        $subject = trim((string) ($payload['subject'] ?? ''));

        if ($title === '' && $subject === '') {
            return $this->json([
                'error' => 'Donnez au moins un titre ou une matiere.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $fileName = null;
        $source = 'local';

        $openAiApiKey = trim((string) ($_SERVER['OPENAI_API_KEY'] ?? $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY')));
        if ($openAiApiKey !== '') {
            $fileName = $this->generateCourseImageWithOpenAi($httpClient, $openAiApiKey, $title, $subject);
            if ($fileName !== null) {
                $source = 'ai';
            }
        }

        if ($fileName === null) {
            $fileName = $this->generateCourseImageLocally($title, $subject);
        }

        if ($fileName === null) {
            return $this->json([
                'error' => 'Generation image indisponible pour le moment.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'filename' => $fileName,
            'url' => '/uploads/images/cours/' . $fileName,
            'source' => $source,
        ]);
    }

    // ================= SHOW =================
    #[Route('/{id}', name: 'app_cours_show', requirements: ['id' => '\d+'])]
    public function show(Cours $cours): Response
    {
        return $this->render('cours/show.html.twig', [
            'cours' => $cours,
        ]);
    }

    // ================= EDIT =================
    #[Route('/{id}/edit', name: 'app_cours_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Cours $cours, EntityManagerInterface $em): Response
    {
        // Store the old image filename before form handling
        $oldImage = $cours->getImage();

        $form = $this->createForm(CoursType::class, $cours);
        // Clear the image field before handling request so form validation works properly
        $form->get('image')->setData(null);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get the uploaded file from the form
            $file = $form->get('image')->getData();

            if ($file) {
                // A new file was uploaded
                $fileName = md5(uniqid()) . '.' . $file->guessExtension();

                try {
                    $file->move(
                        $this->getParameter('images_directory') . '/cours',
                        $fileName
                    );

                    // Set the new filename
                    $cours->setImage($fileName);

                    // Optional: Delete the old image file if it exists
                    if ($oldImage) {
                        $oldImagePath = $this->getParameter('images_directory') . '/cours/' . $oldImage;
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                } catch (FileException $e) {
                    // If upload fails, keep the old image
                    $cours->setImage($oldImage);
                    $this->addFlash('error', 'Erreur lors du telechargement de l image');
                }
            } else {
                // No new file uploaded, keep the old image
                $cours->setImage($oldImage);
            }

            $em->flush();

            $this->addFlash('success', 'Cours modifie avec succes !');

            return $this->redirectToRoute('app_cours_index');
        }

        return $this->render('cours/edit.html.twig', [
            'form' => $form->createView(),
            'cours' => $cours,
        ]);
    }

    // ================= DELETE =================
    #[Route('/{id}/delete', name: 'app_cours_delete', methods: ['POST'])]
    public function delete(Request $request, Cours $cours, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $cours->getId(), $request->request->get('_token'))) {
            try {
                foreach ($cours->getLecons()->toArray() as $lecon) {
                    $em->remove($lecon);
                }

                foreach ($cours->getQuizzes()->toArray() as $quiz) {
                    foreach ($quiz->getQuestions()->toArray() as $question) {
                        $em->remove($question);
                    }
                    $em->remove($quiz);
                }

                $em->remove($cours);
                $em->flush();
                $this->addFlash('success', 'Cours supprime avec succes !');
            } catch (\Throwable) {
                $this->addFlash('error', 'Suppression impossible: ce cours contient encore des donnees liees.');
            }
        }

        return $this->redirectToRoute('app_cours_index');
    }

    #[Route('/student', name: 'app_student')]
    public function student(): Response
    {
        return $this->render('cours/show.html.twig');
    }

    private function generateDraftWithGemini(
        HttpClientInterface $httpClient,
        string $apiKey,
        string $model,
        string $title,
        string $subject,
        ?int $inputLevel
    ): ?array {
        $systemPrompt = <<<'PROMPT'
Tu es un assistant pedagogique.
Tu rediges un brouillon de cours en francais.
Retourne UNIQUEMENT un JSON valide avec les cles:
- description: string (90 a 220 mots)
- objectives: array de 4 strings courts
- plan: array de 5 strings courts
- suggested_level: entier entre 1 et 13
Ne renvoie aucun markdown.
PROMPT;

        $levelContext = $inputLevel !== null ? (string) $inputLevel : 'non precise';
        $userPrompt = "Titre: {$title}\nMatiere: {$subject}\nNiveau saisi: {$levelContext}";
        $prompt = $systemPrompt . "\n\n" . $userPrompt;

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($model),
            rawurlencode($apiKey)
        );

        try {
            $response = $httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'responseMimeType' => 'application/json',
                    ],
                ],
                'timeout' => 20,
            ]);

            $data = $response->toArray(false);
            $content = trim((string) ($data['candidates'][0]['content']['parts'][0]['text'] ?? ''));
            if ($content === '') {
                return null;
            }

            $decoded = $this->decodeJsonPayload($content);
            if (!is_array($decoded)) {
                return null;
            }

            return $this->normalizeDraftPayload($decoded, $title, $subject, $inputLevel);
        } catch (\Throwable) {
            return null;
        }
    }

    private function generateDraftWithOpenAi(
        HttpClientInterface $httpClient,
        string $apiKey,
        string $model,
        string $title,
        string $subject,
        ?int $inputLevel
    ): ?array {
        $systemPrompt = <<<'PROMPT'
Tu es un assistant pedagogique.
Tu rediges un brouillon de cours en francais.
Retourne UNIQUEMENT un JSON valide avec les cles:
- description: string (90 a 220 mots)
- objectives: array de 4 strings courts
- plan: array de 5 strings courts
- suggested_level: entier entre 1 et 13
Ne renvoie aucun markdown.
PROMPT;

        $levelContext = $inputLevel !== null ? (string) $inputLevel : 'non precise';
        $userPrompt = "Titre: {$title}\nMatiere: {$subject}\nNiveau saisi: {$levelContext}";

        try {
            $response = $httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'temperature' => 0.3,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ],
                'timeout' => 20,
            ]);

            $data = $response->toArray(false);
            $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
            if ($content === '') {
                return null;
            }

            $decoded = $this->decodeJsonPayload($content);
            if (!is_array($decoded)) {
                return null;
            }

            return $this->normalizeDraftPayload($decoded, $title, $subject, $inputLevel);
        } catch (\Throwable) {
            return null;
        }
    }

    private function decodeJsonPayload(string $raw): ?array
    {
        $trimmed = trim($raw);
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/si', $trimmed, $matches)) {
            $decoded = json_decode((string) ($matches[1] ?? ''), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function normalizeDraftPayload(array $payload, string $title, string $subject, ?int $inputLevel): array
    {
        $description = trim((string) ($payload['description'] ?? ''));
        if ($description === '') {
            $description = $this->generateDraftLocally($title, $subject, $inputLevel)['description'];
        }
        $description = mb_substr($description, 0, 2500);

        $objectives = [];
        foreach ((array) ($payload['objectives'] ?? []) as $objective) {
            $clean = trim((string) $objective);
            if ($clean !== '') {
                $objectives[] = mb_substr($clean, 0, 220);
            }
            if (count($objectives) >= 6) {
                break;
            }
        }
        if ($objectives === []) {
            $objectives = $this->generateDraftLocally($title, $subject, $inputLevel)['objectives'];
        }

        $plan = [];
        foreach ((array) ($payload['plan'] ?? []) as $step) {
            $clean = trim((string) $step);
            if ($clean !== '') {
                $plan[] = mb_substr($clean, 0, 220);
            }
            if (count($plan) >= 8) {
                break;
            }
        }
        if ($plan === []) {
            $plan = $this->generateDraftLocally($title, $subject, $inputLevel)['plan'];
        }

        $suggestedLevel = $this->normalizeSuggestedLevel($payload['suggested_level'] ?? null);
        if ($suggestedLevel === null) {
            $suggestedLevel = $this->inferSuggestedLevel($title, $subject, $inputLevel);
        }

        return [
            'description' => $description,
            'objectives' => array_values($objectives),
            'plan' => array_values($plan),
            'suggested_level' => $suggestedLevel,
        ];
    }

    private function generateDraftLocally(string $title, string $subject, ?int $inputLevel): array
    {
        $safeTitle = $title !== '' ? $title : 'Cours personnalise';
        $safeSubject = $subject !== '' ? $subject : 'matiere generale';
        $suggestedLevel = $this->inferSuggestedLevel($title, $subject, $inputLevel);

        $description = sprintf(
            "Ce cours \"%s\" en %s propose une progression claire pour des eleves de niveau %d. " .
            "Il combine rappels essentiels, explications simples et activites pratiques afin d'aider l'eleve a gagner en confiance. " .
            "Le contenu avance du concret vers des notions plus structurees, avec des exemples guides et des exercices courts pour verifier la comprehension. " .
            "A la fin, l'eleve dispose d'une vision complete du theme et sait appliquer les notions dans des situations scolaires reelles.",
            $safeTitle,
            $safeSubject,
            $suggestedLevel
        );

        $objectives = [
            sprintf('Comprendre les notions fondamentales de %s.', $safeSubject),
            'Appliquer la methode vue en cours sur des exercices progressifs.',
            'Identifier les erreurs frequentes et savoir les corriger.',
            'Resoudre une tache finale en autonomie avec une demarche claire.',
        ];

        $plan = [
            'Introduction du theme et pre-requis utiles.',
            sprintf('Presentation des concepts cles en %s.', $safeSubject),
            'Exemples guides pas a pas.',
            'Serie d exercices d application et remediation.',
            'Synthese finale et mini evaluation.',
        ];

        return [
            'description' => $description,
            'objectives' => $objectives,
            'plan' => $plan,
            'suggested_level' => $suggestedLevel,
        ];
    }

    private function inferSuggestedLevel(string $title, string $subject, ?int $inputLevel): int
    {
        if ($inputLevel !== null) {
            return $inputLevel;
        }

        $full = mb_strtolower(trim($title . ' ' . $subject));

        $map = [
            '/cp|ce1|ce2|primaire/' => 2,
            '/cm1/' => 4,
            '/cm2/' => 5,
            '/6e|6eme/' => 6,
            '/5e|5eme/' => 7,
            '/4e|4eme/' => 8,
            '/3e|3eme/' => 9,
            '/2nde|seconde/' => 10,
            '/1ere|premiere/' => 11,
            '/terminale|bac/' => 12,
        ];

        foreach ($map as $pattern => $level) {
            if (preg_match($pattern, $full)) {
                return $level;
            }
        }

        return 7;
    }

    private function normalizeSuggestedLevel(mixed $level): ?int
    {
        if ($level === null || $level === '') {
            return null;
        }

        if (!is_numeric($level)) {
            return null;
        }

        $value = (int) $level;
        if ($value < 1) {
            $value = 1;
        }
        if ($value > 13) {
            $value = 13;
        }

        return $value;
    }

    private function buildStructuredDescription(string $description, array $objectives, array $plan): string
    {
        $description = trim($description);
        $lines = [];

        if ($description !== '') {
            $lines[] = $description;
        }

        if ($objectives !== []) {
            $lines[] = '';
            $lines[] = 'Objectifs pedagogiques:';
            foreach ($objectives as $objective) {
                $clean = trim((string) $objective);
                if ($clean !== '') {
                    $lines[] = '- ' . $clean;
                }
            }
        }

        if ($plan !== []) {
            $lines[] = '';
            $lines[] = 'Plan du cours:';
            foreach ($plan as $index => $step) {
                $clean = trim((string) $step);
                if ($clean !== '') {
                    $lines[] = sprintf('%d. %s', $index + 1, $clean);
                }
            }
        }

        return trim(implode("\n", $lines));
    }

    private function sanitizeGeneratedImageFilename(string $raw): ?string
    {
        $fileName = trim($raw);
        if ($fileName === '') {
            return null;
        }

        if ($fileName !== basename($fileName)) {
            return null;
        }

        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $fileName)) {
            return null;
        }

        if (!preg_match('/\.(png|jpg|jpeg|webp|svg)$/i', $fileName)) {
            return null;
        }

        return $fileName;
    }

    private function isCourseImageAvailable(string $fileName): bool
    {
        $imagesDirectory = (string) $this->getParameter('images_directory');
        $targetPath = rtrim($imagesDirectory, '/\\') . DIRECTORY_SEPARATOR . 'cours' . DIRECTORY_SEPARATOR . $fileName;

        return is_file($targetPath);
    }

    private function generateCourseImageWithOpenAi(
        HttpClientInterface $httpClient,
        string $apiKey,
        string $title,
        string $subject
    ): ?string {
        $prompt = $this->buildCourseImagePrompt($title, $subject);
        $model = trim((string) ($_SERVER['OPENAI_IMAGE_MODEL'] ?? $_ENV['OPENAI_IMAGE_MODEL'] ?? getenv('OPENAI_IMAGE_MODEL')));
        if ($model === '') {
            $model = 'gpt-image-1';
        }

        try {
            $response = $httpClient->request('POST', 'https://api.openai.com/v1/images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'prompt' => $prompt,
                    'size' => '1024x1024',
                ],
                'timeout' => 40,
            ]);

            $data = $response->toArray(false);
            $b64Payload = trim((string) ($data['data'][0]['b64_json'] ?? ''));
            if ($b64Payload === '') {
                return null;
            }

            $binary = base64_decode($b64Payload, true);
            if (!is_string($binary) || $binary === '') {
                return null;
            }

            $fileName = $this->buildCourseImageFilename('png');
            if (!$this->writeCourseImage($fileName, $binary)) {
                return null;
            }

            return $fileName;
        } catch (\Throwable) {
            return null;
        }
    }

    private function generateCourseImageLocally(string $title, string $subject): ?string
    {
        $courseTitle = $title !== '' ? $title : 'Cours';
        $courseSubject = $subject !== '' ? $subject : 'Matiere generale';

        $svg = $this->buildLocalCourseSvg($courseTitle, $courseSubject);
        $fileName = $this->buildCourseImageFilename('svg');

        if (!$this->writeCourseImage($fileName, $svg)) {
            return null;
        }

        return $fileName;
    }

    private function buildCourseImagePrompt(string $title, string $subject): string
    {
        $safeTitle = $title !== '' ? $title : 'Cours scolaire';
        $safeSubject = $subject !== '' ? $subject : 'Matiere generale';
        $profile = $this->pickCreativePromptProfile($safeTitle, $safeSubject);
        $subjectVisualCue = $this->inferSubjectVisualCue($safeSubject);

        return sprintf(
            'Create a highly creative square cover illustration for a kids learning platform. ' .
            'No text, no letters, no logos, no watermark. ' .
            'Course concept: "%s". School subject: %s. ' .
            'Visual style: %s. Scene direction: %s. ' .
            'Include subject symbols: %s. ' .
            'Color palette: %s. Mood: %s. ' .
            'Use layered depth, expressive lighting, rich textures, and strong silhouette readability for thumbnail usage.',
            $safeTitle,
            $safeSubject,
            $profile['style'],
            $profile['scene'],
            $subjectVisualCue,
            $profile['palette'],
            $profile['mood']
        );
    }

    private function buildLocalCourseSvg(string $title, string $subject): string
    {
        $titleLines = $this->wrapSvgLines($title, 24, 2);
        if ($titleLines === []) {
            $titleLines = ['Cours'];
        }

        $subjectLine = mb_substr(trim($subject), 0, 38);
        if ($subjectLine === '') {
            $subjectLine = 'Matiere generale';
        }

        $palette = $this->pickCreativeLocalPalette($title, $subject);
        $seed = abs(crc32(mb_strtolower($title . '|' . $subject)));

        $titleBlocks = [];
        foreach ($titleLines as $index => $line) {
            $y = 462 + ($index * 64);
            $titleBlocks[] = sprintf(
                '<text x="96" y="%d" font-family="Arial, sans-serif" font-size="54" font-weight="700" fill="#ffffff">%s</text>',
                $y,
                $this->escapeSvg($line)
            );
        }

        $orbShapes = [];
        for ($i = 0; $i < 8; $i++) {
            $x = 100 + (($seed + ($i * 137)) % 824);
            $y = 70 + (($seed + ($i * 251)) % 870);
            $r = 36 + (($seed + ($i * 89)) % 112);
            $opacity = 0.10 + ((($seed + ($i * 41)) % 18) / 100);
            $fill = $i % 2 === 0 ? $palette['shapeA'] : $palette['shapeB'];
            $orbShapes[] = sprintf(
                '<circle cx="%d" cy="%d" r="%d" fill="%s" fill-opacity="%.2f" filter="url(#softBlur)"/>',
                $x,
                $y,
                $r,
                $fill,
                $opacity
            );
        }

        $ribbonShapes = [];
        for ($i = 0; $i < 4; $i++) {
            $x = 40 + (($seed + ($i * 173)) % 640);
            $y = 60 + ($i * 220);
            $w = 360 + (($seed + ($i * 67)) % 320);
            $angle = -16 + (($seed + ($i * 19)) % 32);
            $fill = $i % 2 === 0 ? $palette['shapeB'] : $palette['shapeA'];
            $ribbonShapes[] = sprintf(
                '<rect x="%d" y="%d" width="%d" height="42" rx="21" fill="%s" fill-opacity="0.14" transform="rotate(%d %d %d)"/>',
                $x,
                $y,
                $w,
                $fill,
                $angle,
                $x,
                $y
            );
        }

        $subjectBadge = sprintf(
            '%s • Creative cover',
            mb_strtoupper(mb_substr($subjectLine, 0, 28))
        );

        $titleSvg = implode("\n", $titleBlocks);
        $orbSvg = implode("\n", $orbShapes);
        $ribbonSvg = implode("\n", $ribbonShapes);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="1024" viewBox="0 0 1024 1024" role="img" aria-label="Course image">
    <defs>
        <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="{$palette['bg1']}"/>
            <stop offset="55%" stop-color="{$palette['bg2']}"/>
            <stop offset="100%" stop-color="{$palette['bg3']}"/>
        </linearGradient>
        <linearGradient id="panel" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" stop-color="#ffffff" stop-opacity="0.22"/>
            <stop offset="100%" stop-color="#0f172a" stop-opacity="0.28"/>
        </linearGradient>
        <filter id="softBlur" x="-20%" y="-20%" width="140%" height="140%">
            <feGaussianBlur stdDeviation="8"/>
        </filter>
    </defs>
    <rect width="1024" height="1024" rx="64" fill="url(#bg)"/>
    {$ribbonSvg}
    {$orbSvg}
    <rect x="74" y="308" width="876" height="458" rx="40" fill="url(#panel)" stroke="#ffffff" stroke-opacity="0.22"/>
    <text x="96" y="372" font-family="Arial, sans-serif" font-size="28" font-weight="700" fill="#dbeafe">{$this->escapeSvg($subjectBadge)}</text>
    {$titleSvg}
    <text x="96" y="664" font-family="Arial, sans-serif" font-size="34" font-weight="500" fill="#eef2ff">{$this->escapeSvg($subjectLine)}</text>
    <rect x="96" y="706" width="260" height="58" rx="16" fill="#ffffff" fill-opacity="0.24"/>
    <text x="126" y="738" font-family="Arial, sans-serif" font-size="28" font-weight="600" fill="#ffffff">EduKids</text>
</svg>
SVG;
    }

    private function pickCreativePromptProfile(string $title, string $subject): array
    {
        $profiles = [
            [
                'style' => 'cinematic paper-cut collage',
                'scene' => 'a playful expedition inside a giant notebook world with floating school objects',
                'palette' => 'teal, coral, sunflower yellow, midnight blue',
                'mood' => 'joyful, exploratory, imaginative',
            ],
            [
                'style' => 'storybook 3D clay illustration',
                'scene' => 'friendly kid explorers building knowledge towers and bridges between ideas',
                'palette' => 'mint green, peach, cobalt blue, warm cream',
                'mood' => 'warm, magical, optimistic',
            ],
            [
                'style' => 'bold geometric editorial art',
                'scene' => 'dynamic abstract learning universe with motion lines and symbolic educational artifacts',
                'palette' => 'electric blue, orange, cyan, deep navy',
                'mood' => 'energetic, modern, ambitious',
            ],
            [
                'style' => 'whimsical watercolor + vector hybrid',
                'scene' => 'dreamlike classroom landscape with layered clouds, maps, and creative tools',
                'palette' => 'lavender blue, aqua, soft orange, forest teal',
                'mood' => 'calm, inspiring, creative',
            ],
            [
                'style' => 'futuristic isometric illustration',
                'scene' => 'mini knowledge city with glowing pathways and collaborative learning hubs',
                'palette' => 'indigo, neon cyan, rose orange, pearl white',
                'mood' => 'innovative, playful, high-tech',
            ],
        ];

        $count = count($profiles);
        if ($count === 0) {
            return [
                'style' => 'modern educational illustration',
                'scene' => 'colorful school environment',
                'palette' => 'teal, blue, orange, white',
                'mood' => 'uplifting and creative',
            ];
        }

        try {
            $index = random_int(0, $count - 1);
        } catch (\Throwable) {
            $index = abs(crc32(mb_strtolower($title . '|' . $subject))) % $count;
        }

        return $profiles[$index];
    }

    private function inferSubjectVisualCue(string $subject): string
    {
        $normalized = mb_strtolower(trim($subject));

        $map = [
            '/math|mathematique|calcul|algebre|geometrie/' => 'geometric constellations, abacus beads, rulers, compasses, puzzle grids',
            '/francais|langue|lecture|ecriture|arabe|anglais/' => 'story scrolls, speech bubbles, ink strokes, playful alphabet symbols',
            '/svt|science|biologie|chimie|physique/' => 'microscopes, molecules, planets, plants, gentle science lab motifs',
            '/histoire|geo|geographie|civique/' => 'maps, timelines, landmarks, travel paths, cultural icons',
            '/informatique|code|programmation|robotique/' => 'friendly robots, circuitry paths, modular blocks, holographic screens',
            '/musique|art|dessin|theatre/' => 'instruments, paint swirls, stage lights, handcrafted textures',
            '/sport|education physique/' => 'motion trails, fields, team symbols, kinetic lines',
        ];

        foreach ($map as $pattern => $cue) {
            if (preg_match($pattern, $normalized)) {
                return $cue;
            }
        }

        return 'books, stars, creative tools, exploration paths, abstract school motifs';
    }

    private function pickCreativeLocalPalette(string $title, string $subject): array
    {
        $palettes = [
            ['bg1' => '#0ea5a3', 'bg2' => '#2563eb', 'bg3' => '#1e293b', 'shapeA' => '#ffffff', 'shapeB' => '#f59e0b'],
            ['bg1' => '#f97316', 'bg2' => '#ec4899', 'bg3' => '#334155', 'shapeA' => '#fde68a', 'shapeB' => '#e0f2fe'],
            ['bg1' => '#14b8a6', 'bg2' => '#7c3aed', 'bg3' => '#0f172a', 'shapeA' => '#fef3c7', 'shapeB' => '#dbeafe'],
            ['bg1' => '#22c55e', 'bg2' => '#0ea5e9', 'bg3' => '#1d4ed8', 'shapeA' => '#ecfeff', 'shapeB' => '#fde68a'],
            ['bg1' => '#e11d48', 'bg2' => '#7c3aed', 'bg3' => '#0f172a', 'shapeA' => '#ffe4e6', 'shapeB' => '#cffafe'],
        ];

        $count = count($palettes);
        if ($count === 0) {
            return ['bg1' => '#0ea5a3', 'bg2' => '#2563eb', 'bg3' => '#1e293b', 'shapeA' => '#ffffff', 'shapeB' => '#f59e0b'];
        }

        try {
            $index = random_int(0, $count - 1);
        } catch (\Throwable) {
            $index = abs(crc32(mb_strtolower($title . '|' . $subject))) % $count;
        }

        return $palettes[$index];
    }

    private function writeCourseImage(string $fileName, string $content): bool
    {
        $imagesDirectory = (string) $this->getParameter('images_directory');
        $targetDirectory = rtrim($imagesDirectory, '/\\') . DIRECTORY_SEPARATOR . 'cours';

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            return false;
        }

        $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $fileName;

        return file_put_contents($targetPath, $content) !== false;
    }

    private function buildCourseImageFilename(string $extension): string
    {
        $safeExt = strtolower(preg_replace('/[^a-z0-9]/i', '', $extension));
        if ($safeExt === '') {
            $safeExt = 'png';
        }

        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (\Throwable) {
            $suffix = substr(md5(uniqid('ai_', true)), 0, 8);
        }

        return sprintf('ai_course_%s_%s.%s', date('YmdHis'), $suffix, $safeExt);
    }

    private function wrapSvgLines(string $text, int $maxCharsPerLine, int $maxLines): array
    {
        $clean = preg_replace('/\s+/', ' ', trim($text));
        if (!is_string($clean) || $clean === '') {
            return [];
        }

        $words = preg_split('/\s+/', $clean);
        if (!is_array($words)) {
            return [];
        }

        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $word = trim((string) $word);
            if ($word === '') {
                continue;
            }

            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($candidate) <= $maxCharsPerLine) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                if (count($lines) >= $maxLines) {
                    break;
                }
            }

            $current = mb_substr($word, 0, $maxCharsPerLine);
        }

        if (count($lines) < $maxLines && $current !== '') {
            $lines[] = $current;
        }

        return array_slice($lines, 0, $maxLines);
    }

    private function escapeSvg(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
