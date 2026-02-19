<?php

namespace App\Controller;

use App\Entity\Lecon;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpClient\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Attribute\Route;

final class LeconTutorController extends AbstractController
{
    private array $binaryCache = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%env(string:OPENAI_API_KEY)%')]
        private readonly string $openAiApiKey,
        #[Autowire('%env(string:OPENAI_MODEL)%')]
        private readonly string $openAiModel,
    ) {
    }

    #[Route('/lecon/{id}/tutor/ask', name: 'app_lecon_tutor_ask', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function ask(Lecon $lecon, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $question = trim((string) ($payload['question'] ?? ''));

        if ($question === '') {
            return $this->json([
                'error' => 'Question vide',
            ], Response::HTTP_BAD_REQUEST);
        }

        $context = $this->buildLessonContext($lecon);
        $answer = $this->askOpenAi($question, $context);

        return $this->json([
            'answer' => $this->sanitizeUtf8($answer),
        ]);
    }

    #[Route('/lecon/{id}/tutor/summary', name: 'app_lecon_tutor_summary', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function summary(Lecon $lecon, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $rawLevel = mb_strtolower(trim((string) ($payload['level'] ?? 'standard')));
        $level = $this->normalizeSummaryLevel($rawLevel);

        if ($level === null) {
            return $this->json([
                'error' => 'Niveau invalide. Utilisez: facile, standard ou avance.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $context = $this->buildLessonContext($lecon);
        $summary = $this->summarizeLessonByLevel($context, $level);

        return $this->json([
            'level' => $level,
            'summary' => $this->sanitizeUtf8($summary),
        ]);
    }

    #[Route('/lecon/{id}/tutor/ocr', name: 'app_lecon_tutor_ocr', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function ocr(Lecon $lecon): JsonResponse
    {
        $mediaUrl = $lecon->getMediaUrl();
        $pdfPath = $this->resolveLocalPdfPath($mediaUrl);
        if ($pdfPath === null) {
            return $this->json([
                'error' => "Impossible de lancer l'OCR sur ce PDF.",
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->hasAnyOcrEngine()) {
            return $this->json([
                'error' => "Aucun moteur OCR detecte. Installez 'ocrmypdf' (recommande) ou 'pdftotext' sur le serveur.",
                'code' => 'OCR_ENGINE_MISSING',
                'pdf_url' => $mediaUrl,
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $sidecarPath = $this->getOcrSidecarPath((string) $mediaUrl);
        $sidecarDir = dirname($sidecarPath);
        if (!is_dir($sidecarDir) && !@mkdir($sidecarDir, 0775, true) && !is_dir($sidecarDir)) {
            return $this->json([
                'error' => "Impossible de preparer le dossier OCR.",
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $result = $this->runOcrPipeline($pdfPath, $sidecarPath);
        if (!$result['ok']) {
            return $this->json([
                'error' => (string) $result['message'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $ocrText = $this->sanitizeUtf8((string) (@file_get_contents($sidecarPath) ?: ''));
        if ($ocrText === '') {
            return $this->json([
                'error' => "OCR termine, mais aucun texte n'a ete extrait.",
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->isUsefulExtractedText($ocrText) || $this->isMostlyGibberish($ocrText)) {
            return $this->json([
                'error' => "OCR termine, mais le texte obtenu reste illisible.",
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'answer' => "OCR termine avec succes. Vous pouvez maintenant redemander au tuteur de lire/resumer le PDF.",
        ]);
    }

    private function buildLessonContext(Lecon $lecon): string
    {
        $coursTitle = $lecon->getCours()?->getTitre() ?? 'Sans cours';
        $pdfText = $this->extractPdfText($lecon->getMediaUrl());

        $parts = [
            "Cours: {$coursTitle}",
            "Lecon: {$lecon->getTitre()}",
            "Ordre: {$lecon->getOrdre()}",
            "Type media: {$lecon->getMediaType()}",
            'PDF URL: '.($lecon->getMediaUrl() ?? 'N/A'),
            'Video URL: '.($lecon->getVideoUrl() ?? 'N/A'),
            'YouTube URL: '.($lecon->getYoutubeUrl() ?? 'N/A'),
        ];

        if ($pdfText !== '') {
            $parts[] = 'Extrait PDF: '.$pdfText;
            $parts[] = 'PDF Extraction: ok';
        } else {
            $parts[] = 'PDF Extraction: '.$this->diagnosePdfExtractionFailure($lecon->getMediaUrl());
        }

        return implode("\n", $parts);
    }

    private function extractPdfText(?string $mediaUrl): string
    {
        if (!$mediaUrl || !str_ends_with(strtolower($mediaUrl), '.pdf')) {
            return '';
        }

        $ocrText = $this->readOcrText((string) $mediaUrl);
        if ($ocrText !== '') {
            return mb_substr($ocrText, 0, 6000);
        }

        $content = $this->loadPdfBinary($mediaUrl);
        if ($content === false) {
            return '';
        }

        $candidates = [];

        $streamText = $this->extractTextFromPdfStreams($content);
        if ($streamText !== '') {
            $candidates[] = $streamText;
        }

        $literalText = $this->extractTextFromPdfStringLiterals($content);
        if ($literalText !== '') {
            $candidates[] = $literalText;
        }

        if ($candidates === []) {
            return '';
        }

        $text = $this->selectBestExtractedText($candidates);
        $text = $this->sanitizeUtf8($text);

        if ($text === '') {
            return '';
        }

        if (!$this->isUsefulExtractedText($text)) {
            return '';
        }

        if ($this->isMostlyGibberish($text)) {
            return '';
        }

        return mb_substr($text, 0, 6000);
    }

    private function extractTextFromPdfStreams(string $pdfContent): string
    {
        if (!preg_match_all('/<<(.*?)>>\s*stream[\r\n]+(.*?)[\r\n]+endstream/s', $pdfContent, $matches, PREG_SET_ORDER)) {
            return '';
        }

        $chunks = [];
        foreach ($matches as $match) {
            $dict = (string) ($match[1] ?? '');
            $stream = (string) ($match[2] ?? '');
            $decodedStream = $this->decodePdfStream($stream, $dict);
            if ($decodedStream === '') {
                continue;
            }

            $chunks = array_merge($chunks, $this->extractTextChunksFromContentStream($decodedStream));
        }

        if ($chunks === []) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', implode(' ', $chunks)) ?? '');
    }

    private function extractTextFromPdfStringLiterals(string $pdfContent): string
    {
        $limitedContent = substr($pdfContent, 0, 300_000);
        preg_match_all('/\(([^()]*)\)/', $limitedContent, $matches);
        if (empty($matches[1])) {
            return '';
        }

        $decodedChunks = array_map([$this, 'decodePdfString'], $matches[1]);

        return trim(preg_replace('/\s+/u', ' ', implode(' ', $decodedChunks)) ?? '');
    }

    private function selectBestExtractedText(array $candidates): string
    {
        $best = '';
        $bestScore = -1;

        foreach ($candidates as $candidate) {
            $normalized = trim($this->sanitizeUtf8((string) $candidate));
            if ($normalized === '') {
                continue;
            }

            preg_match_all('/[\p{L}\p{N}]/u', $normalized, $alnum);
            $score = count($alnum[0] ?? []);
            if ($score > $bestScore) {
                $best = $normalized;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function decodePdfStream(string $stream, string $dictionary): string
    {
        if (!preg_match('/\/Filter\s*(\[[^\]]+\]|\/[A-Za-z0-9]+)/s', $dictionary, $filterMatch)) {
            return $stream;
        }

        $filterExpr = (string) ($filterMatch[1] ?? '');
        preg_match_all('/\/([A-Za-z0-9]+)/', $filterExpr, $nameMatches);
        $filters = $nameMatches[1] ?? [];
        if ($filters === []) {
            return '';
        }

        $decoded = $stream;
        foreach ($filters as $filter) {
            if ($filter === 'FlateDecode') {
                $decoded = $this->tryFlateDecode($decoded);
                if ($decoded === '') {
                    return '';
                }
                continue;
            }

            return '';
        }

        return $decoded;
    }

    private function tryFlateDecode(string $data): string
    {
        $attempts = [
            @gzuncompress($data),
            @gzinflate($data),
            @zlib_decode($data),
            strlen($data) > 2 ? @gzinflate(substr($data, 2)) : false,
        ];

        foreach ($attempts as $attempt) {
            if (is_string($attempt) && $attempt !== '') {
                return $attempt;
            }
        }

        return '';
    }

    private function extractTextChunksFromContentStream(string $content): array
    {
        $chunks = [];
        if (preg_match_all('/BT(.*?)ET/s', $content, $sections)) {
            foreach ($sections[1] as $section) {
                $chunks = array_merge($chunks, $this->extractPdfStringsFromSection((string) $section));
            }

            if ($chunks !== []) {
                return $chunks;
            }
        }

        return $this->extractPdfStringsFromSection($content);
    }

    private function extractPdfStringsFromSection(string $section): array
    {
        $results = [];
        $length = strlen($section);

        for ($i = 0; $i < $length; $i++) {
            $char = $section[$i];

            if ($char === '(') {
                $start = $i + 1;
                $depth = 1;
                $escaped = false;
                $closed = false;

                for ($j = $start; $j < $length; $j++) {
                    $c = $section[$j];

                    if ($escaped) {
                        $escaped = false;
                        continue;
                    }

                    if ($c === '\\') {
                        $escaped = true;
                        continue;
                    }

                    if ($c === '(') {
                        $depth++;
                        continue;
                    }

                    if ($c === ')') {
                        $depth--;
                        if ($depth === 0) {
                            $raw = substr($section, $start, $j - $start);
                            $decoded = $this->decodePdfString($raw);
                            if ($decoded !== '') {
                                $results[] = $decoded;
                            }
                            $i = $j;
                            $closed = true;
                            break;
                        }
                    }
                }

                if (!$closed) {
                    break;
                }

                continue;
            }

            if ($char === '<' && ($i + 1 < $length) && $section[$i + 1] !== '<') {
                $end = strpos($section, '>', $i + 1);
                if ($end === false) {
                    continue;
                }

                $hex = substr($section, $i + 1, $end - $i - 1);
                $decoded = $this->decodePdfHexString($hex);
                if ($decoded !== '') {
                    $results[] = $decoded;
                }

                $i = $end;
            }
        }

        return $results;
    }

    private function decodePdfHexString(string $hex): string
    {
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', $hex) ?? '';
        if ($hex === '') {
            return '';
        }

        if ((strlen($hex) % 2) === 1) {
            $hex .= '0';
        }

        $binary = @hex2bin($hex);
        if ($binary === false || $binary === '') {
            return '';
        }

        return $this->sanitizeUtf8($binary);
    }

    private function loadPdfBinary(string $mediaUrl): string|false
    {
        if (preg_match('/^https?:\/\//i', $mediaUrl)) {
            try {
                $response = $this->httpClient->request('GET', $mediaUrl, [
                    'max_redirects' => 3,
                    'timeout' => 8,
                ]);

                if ($response->getStatusCode() >= 400) {
                    return false;
                }

                return $response->getContent(false);
            } catch (\Throwable) {
                return false;
            }
        }

        $pdfPath = $this->projectDir.'/public'.$mediaUrl;
        if (!is_readable($pdfPath)) {
            return false;
        }

        return @file_get_contents($pdfPath, false, null, 0, 1_500_000);
    }

    private function diagnosePdfExtractionFailure(?string $mediaUrl): string
    {
        if (!$mediaUrl || !str_ends_with(strtolower($mediaUrl), '.pdf')) {
            return 'non_pdf';
        }

        if ($this->readOcrText((string) $mediaUrl) !== '') {
            return 'ok';
        }

        $content = $this->loadPdfBinary($mediaUrl);
        if ($content === false || $content === '') {
            return 'unreadable_file';
        }

        if (preg_match('/\/Encrypt\b/s', $content)) {
            return 'encrypted';
        }

        $imageCount = preg_match_all('/\/Subtype\s*\/Image\b/s', $content, $m1);
        $textOpsCount = preg_match_all('/\bBT\b|\bTj\b|\bTJ\b/s', $content, $m2);
        if (($imageCount ?: 0) > 0 && ($textOpsCount ?: 0) < 3) {
            return 'likely_scanned';
        }

        return 'no_text_found';
    }

    private function decodePdfString(string $value): string
    {
        $value = preg_replace("/\\\\\r?\n/", '', $value) ?? $value;
        $value = preg_replace_callback('/\\\\([0-7]{1,3})/', static function (array $match): string {
            return chr(octdec($match[1]) % 256);
        }, $value) ?? $value;
        $value = str_replace(['\\n', '\\r', '\\t', '\\f', '\\b'], ' ', $value);
        $value = preg_replace('/\\\\([()\\\\])/', '$1', $value) ?? $value;
        $value = preg_replace('/\\\\./', '', $value) ?? $value;

        return trim($this->sanitizeUtf8($value));
    }

    private function askOpenAi(string $question, string $context): string
    {
        if (trim($this->openAiApiKey) === '') {
            return $this->answerFromLocalContext($question, $context);
        }

        $systemPrompt = <<<PROMPT
Tu es un tuteur pedagogique vocal.
Regles strictes:
- Reponds uniquement a partir du contexte fourni.
- Si la question de l'eleve est mal formulee, commence par proposer une version corrigee en une phrase courte.
- Si l'eleve demande de lire/resumer le PDF et que "Extrait PDF" est disponible, donne un court resume base dessus.
- Si l'eleve demande de lire/resumer le PDF et qu'il n'y a pas "Extrait PDF", indique que l'extraction automatique a echoue et renvoie la ligne "PDF URL".
- Si "PDF Extraction" vaut "likely_scanned" ou "encrypted", explique clairement cette cause avant de renvoyer "PDF URL".
- Si l'information n'existe pas dans le contexte, dis: "Je ne trouve pas cette information dans la lecon."
- Reponse concise, claire, en francais.
PROMPT;

        try {
            $model = trim($this->openAiModel) !== '' ? $this->openAiModel : 'gpt-4o-mini';

            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->openAiApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        [
                            'role' => 'user',
                            'content' => "Contexte de la lecon:\n{$context}\n\nQuestion de l'eleve:\n{$question}",
                        ],
                    ],
                ],
            ]);

            $data = $response->toArray(false);
            $answer = trim((string) ($data['choices'][0]['message']['content'] ?? ''));

            if ($answer === '') {
                return "Je ne trouve pas cette information dans la lecon.";
            }

            return $answer;
        } catch (ExceptionInterface|\Throwable $e) {
            return "Le tuteur IA est temporairement indisponible. Reessayez dans quelques instants.";
        }
    }

    private function normalizeSummaryLevel(string $level): ?string
    {
        return match ($level) {
            'facile', 'debutant', 'easy' => 'facile',
            'standard', 'normal', 'intermediaire' => 'standard',
            'avance', 'advanced', 'expert' => 'avance',
            default => null,
        };
    }

    private function summarizeLessonByLevel(string $context, string $level): string
    {
        if (trim($this->openAiApiKey) !== '') {
            return $this->summarizeWithOpenAi($context, $level);
        }

        return $this->summarizeFromLocalContext($context, $level);
    }

    private function summarizeWithOpenAi(string $context, string $level): string
    {
        $levelInstruction = match ($level) {
            'facile' => "Niveau facile: phrases courtes, vocabulaire simple, 3 a 4 points maximum.",
            'standard' => "Niveau standard: resume equilibre, 4 a 6 points clairs.",
            'avance' => "Niveau avance: inclure notions importantes, liens entre concepts, 5 a 8 points.",
            default => "Niveau standard.",
        };

        $systemPrompt = <<<PROMPT
Tu es un assistant pedagogique.
Regles strictes:
- Reponds uniquement a partir du contexte fourni.
- N'invente aucune information.
- Reponds en francais.
- Format obligatoire: un titre court puis une liste de points.
- Si le contexte est insuffisant, reponds exactement: "Je ne trouve pas assez d'informations dans la lecon."
- {$levelInstruction}
PROMPT;

        try {
            $model = trim($this->openAiModel) !== '' ? $this->openAiModel : 'gpt-4o-mini';

            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->openAiApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        [
                            'role' => 'user',
                            'content' => "Contexte de la lecon:\n{$context}\n\nNiveau demande: {$level}",
                        ],
                    ],
                ],
            ]);

            $data = $response->toArray(false);
            $summary = trim((string) ($data['choices'][0]['message']['content'] ?? ''));

            if ($summary !== '') {
                return $summary;
            }
        } catch (ExceptionInterface|\Throwable) {
            // Fallback local below.
        }

        return $this->summarizeFromLocalContext($context, $level);
    }

    private function summarizeFromLocalContext(string $context, string $level): string
    {
        $pdfText = $this->extractPdfTextFromContext($context);
        if ($pdfText === '') {
            $pdfUrl = $this->extractPdfUrlFromContext($context);
            $pdfStatus = $this->extractPdfExtractionStatusFromContext($context);

            if ($pdfStatus === 'likely_scanned') {
                if ($pdfUrl !== '') {
                    return "Impossible de generer le resume: ce PDF semble scanne (image). Ouvrez le document ici: ".$pdfUrl;
                }

                return "Impossible de generer le resume: ce PDF semble scanne (image).";
            }

            if ($pdfStatus === 'encrypted') {
                if ($pdfUrl !== '') {
                    return "Impossible de generer le resume: ce PDF semble protege/chiffre. Ouvrez le document ici: ".$pdfUrl;
                }

                return "Impossible de generer le resume: ce PDF semble protege/chiffre.";
            }

            if ($pdfUrl !== '') {
                return "Impossible de generer le resume automatiquement. Ouvrez le PDF ici: ".$pdfUrl;
            }

            return "Je ne trouve pas assez d'informations dans la lecon.";
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $pdfText) ?: [];
        $targetCount = match ($level) {
            'facile' => 3,
            'standard' => 5,
            'avance' => 7,
            default => 4,
        };
        $targetLength = match ($level) {
            'facile' => 120,
            'standard' => 170,
            'avance' => 220,
            default => 160,
        };

        $selected = [];
        foreach ($sentences as $sentence) {
            $sentence = trim($this->sanitizeUtf8($sentence));
            if ($sentence === '' || $this->isMostlyGibberish($sentence)) {
                continue;
            }

            $selected[] = mb_substr($sentence, 0, $targetLength);
            if (count($selected) >= $targetCount) {
                break;
            }
        }

        if ($selected === []) {
            return "Je ne trouve pas assez d'informations dans la lecon.";
        }

        $title = match ($level) {
            'facile' => 'Resume niveau facile',
            'standard' => 'Resume niveau standard',
            'avance' => 'Resume niveau avance',
            default => 'Resume',
        };

        $lines = [$title.':'];
        foreach ($selected as $item) {
            $lines[] = '- '.$item;
        }

        if ($level === 'avance') {
            $lines[] = '- Analyse conseillee: reliez ces points aux objectifs globaux du cours.';
        }

        return implode("\n", $lines);
    }

    private function answerFromLocalContext(string $question, string $context): string
    {
        $pdfText = $this->extractPdfTextFromContext($context);
        $normalizedQuestion = mb_strtolower(trim($question));
        $normalizedQuestion = preg_replace('/\bdf\b/u', 'pdf', $normalizedQuestion) ?? $normalizedQuestion;
        $normalizedQuestion = preg_replace('/\bpp\b/u', 'pdf', $normalizedQuestion) ?? $normalizedQuestion;
        if ($this->isRephraseRequest($normalizedQuestion)) {
            return $this->buildRephraseAnswer($question);
        }

        if ($this->isSummaryQuestion($normalizedQuestion)) {
            if ($pdfText === '') {
                $pdfUrl = $this->extractPdfUrlFromContext($context);
                $pdfStatus = $this->extractPdfExtractionStatusFromContext($context);

                if ($pdfStatus === 'likely_scanned') {
                    if ($pdfUrl !== '') {
                        return "Ce PDF semble etre un document scanne (image), donc je ne peux pas en extraire automatiquement le texte. Vous pouvez l'ouvrir ici: ".$pdfUrl;
                    }

                    return "Ce PDF semble etre un document scanne (image), donc je ne peux pas en extraire automatiquement le texte.";
                }

                if ($pdfStatus === 'encrypted') {
                    if ($pdfUrl !== '') {
                        return "Ce PDF semble protege/chiffre, donc je ne peux pas en lire automatiquement le contenu. Vous pouvez l'ouvrir ici: ".$pdfUrl;
                    }

                    return "Ce PDF semble protege/chiffre, donc je ne peux pas en lire automatiquement le contenu.";
                }

                if ($pdfUrl !== '') {
                    return "Je n'arrive pas a extraire automatiquement le texte du PDF. Vous pouvez le lire ici: ".$pdfUrl;
                }

                return "Je ne trouve pas cette information dans la lecon.";
            }

            if ($this->isMostlyGibberish($pdfText)) {
                $pdfUrl = $this->extractPdfUrlFromContext($context);
                if ($pdfUrl !== '') {
                    return "Le texte extrait du PDF est illisible. Vous pouvez ouvrir le PDF ici: ".$pdfUrl;
                }

                return "Je n'arrive pas a lire correctement le contenu du PDF.";
            }

            return 'Voici un extrait du contenu PDF: '.$this->buildReadableExcerpt($pdfText);
        }

        if ($pdfText === '') {
            return "Je ne trouve pas cette information dans la lecon.";
        }

        $keywords = $this->extractKeywords($normalizedQuestion);
        if ($keywords === []) {
            return "Je ne trouve pas cette information dans la lecon.";
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $pdfText) ?: [];
        $matches = [];
        foreach ($sentences as $sentence) {
            $normalizedSentence = mb_strtolower($sentence);
            foreach ($keywords as $keyword) {
                if (str_contains($normalizedSentence, $keyword)) {
                    $matches[] = trim($sentence);
                    break;
                }
            }
            if (count($matches) >= 3) {
                break;
            }
        }

        if ($matches === []) {
            return "Je ne trouve pas cette information dans la lecon.";
        }

        $answer = implode(' ', $matches);

        return mb_substr($answer, 0, 700);
    }

    private function extractPdfTextFromContext(string $context): string
    {
        $prefix = 'Extrait PDF: ';
        $start = mb_strpos($context, $prefix);
        if ($start === false) {
            return '';
        }

        return trim(mb_substr($context, $start + mb_strlen($prefix)));
    }

    private function extractPdfUrlFromContext(string $context): string
    {
        if (!preg_match('/^PDF URL:\s*(.+)$/mi', $context, $matches)) {
            return '';
        }

        $url = trim((string) ($matches[1] ?? ''));
        if ($url === '' || mb_strtolower($url) === 'n/a') {
            return '';
        }

        return $url;
    }

    private function extractPdfExtractionStatusFromContext(string $context): string
    {
        if (!preg_match('/^PDF Extraction:\s*(.+)$/mi', $context, $matches)) {
            return '';
        }

        return trim((string) ($matches[1] ?? ''));
    }

    private function resolveLocalPdfPath(?string $mediaUrl): ?string
    {
        if (!$mediaUrl || !str_ends_with(strtolower($mediaUrl), '.pdf')) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $mediaUrl)) {
            return null;
        }

        $pdfPath = $this->projectDir.'/public'.$mediaUrl;
        if (!is_readable($pdfPath)) {
            return null;
        }

        return $pdfPath;
    }

    private function getOcrSidecarPath(string $mediaUrl): string
    {
        return $this->projectDir.'/var/ocr/'.md5($mediaUrl).'.txt';
    }

    private function readOcrText(string $mediaUrl): string
    {
        $path = $this->getOcrSidecarPath($mediaUrl);
        if (!is_readable($path)) {
            return '';
        }

        $text = $this->sanitizeUtf8((string) (@file_get_contents($path) ?: ''));
        if ($text === '') {
            return '';
        }

        if (!$this->isUsefulExtractedText($text) || $this->isMostlyGibberish($text)) {
            return '';
        }

        return $text;
    }

    private function runOcrPipeline(string $pdfPath, string $sidecarPath): array
    {
        @unlink($sidecarPath);

        $ocrmypdfBin = $this->resolveCommandBinary('ocrmypdf');
        if ($ocrmypdfBin !== null) {
            $tmpOutput = tempnam(sys_get_temp_dir(), 'ocrpdf_');
            $tmpOutputPdf = $tmpOutput === false ? sys_get_temp_dir().'/ocr_output_'.uniqid('', true).'.pdf' : $tmpOutput.'.pdf';
            if ($tmpOutput !== false) {
                @unlink($tmpOutput);
            }

            $process = new Process([
                $ocrmypdfBin,
                '--force-ocr',
                '--skip-text',
                '--sidecar',
                $sidecarPath,
                $pdfPath,
                $tmpOutputPdf,
            ]);
            $process->setTimeout(180);
            $process->run();
            @unlink($tmpOutputPdf);

            if ($process->isSuccessful() && is_readable($sidecarPath)) {
                return ['ok' => true, 'message' => 'ok'];
            }

            return ['ok' => false, 'message' => "OCR a echoue avec ocrmypdf."];
        }

        $pdftotextBin = $this->resolveCommandBinary('pdftotext');
        if ($pdftotextBin !== null) {
            $process = new Process([
                $pdftotextBin,
                '-layout',
                $pdfPath,
                $sidecarPath,
            ]);
            $process->setTimeout(120);
            $process->run();

            if ($process->isSuccessful() && is_readable($sidecarPath)) {
                return ['ok' => true, 'message' => 'ok'];
            }

            return ['ok' => false, 'message' => "Extraction texte a echoue avec pdftotext."];
        }

        return [
            'ok' => false,
            'message' => "Aucun moteur OCR detecte. Installez 'ocrmypdf' (recommande) ou 'pdftotext' sur le serveur.",
        ];
    }

    private function hasAnyOcrEngine(): bool
    {
        return $this->resolveCommandBinary('ocrmypdf') !== null || $this->resolveCommandBinary('pdftotext') !== null;
    }

    private function isCommandAvailable(string $command): bool
    {
        return $this->resolveCommandBinary($command) !== null;
    }

    private function resolveCommandBinary(string $command): ?string
    {
        if (array_key_exists($command, $this->binaryCache)) {
            return $this->binaryCache[$command];
        }

        $configured = $this->getConfiguredBinaryPath($command);
        if ($configured !== null) {
            return $this->binaryCache[$command] = $configured;
        }

        $probe = DIRECTORY_SEPARATOR === '\\'
            ? new Process(['where.exe', $command])
            : new Process(['sh', '-lc', 'command -v '.escapeshellarg($command)]);

        $probe->setTimeout(10);
        $probe->run();

        if ($probe->isSuccessful()) {
            $output = trim($this->sanitizeUtf8($probe->getOutput()));
            if ($output !== '') {
                $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
                foreach ($lines as $line) {
                    $candidate = trim($line);
                    if ($candidate === '') {
                        continue;
                    }

                    if (DIRECTORY_SEPARATOR === '\\') {
                        if (is_file($candidate) && is_readable($candidate)) {
                            return $this->binaryCache[$command] = $candidate;
                        }
                    } elseif (is_file($candidate) && is_executable($candidate)) {
                        return $this->binaryCache[$command] = $candidate;
                    }
                }
            }
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            $fallback = $this->findWindowsBinaryInKnownLocations($command);
            if ($fallback !== null) {
                return $this->binaryCache[$command] = $fallback;
            }
        }

        return $this->binaryCache[$command] = null;
    }

    private function getConfiguredBinaryPath(string $command): ?string
    {
        $envNames = match ($command) {
            'ocrmypdf' => ['OCRMYPDF_BIN', 'OCR_OCRMYPDF_BIN'],
            'pdftotext' => ['PDFTOTEXT_BIN', 'OCR_PDFTOTEXT_BIN'],
            default => [],
        };

        foreach ($envNames as $envName) {
            $value = trim((string) ($_SERVER[$envName] ?? $_ENV[$envName] ?? getenv($envName)));
            if ($value === '') {
                continue;
            }

            if (DIRECTORY_SEPARATOR === '\\') {
                if (is_file($value) && is_readable($value)) {
                    return $value;
                }
                continue;
            }

            if (is_file($value) && is_executable($value)) {
                return $value;
            }
        }

        return null;
    }

    private function findWindowsBinaryInKnownLocations(string $command): ?string
    {
        $exe = $command.'.exe';
        $patterns = [];

        $localAppData = trim((string) ($_SERVER['LOCALAPPDATA'] ?? $_ENV['LOCALAPPDATA'] ?? getenv('LOCALAPPDATA')));
        $programFiles = trim((string) ($_SERVER['ProgramFiles'] ?? $_ENV['ProgramFiles'] ?? getenv('ProgramFiles')));
        $programFilesX86 = trim((string) ($_SERVER['ProgramFiles(x86)'] ?? $_ENV['ProgramFiles(x86)'] ?? getenv('ProgramFiles(x86)')));

        if ($localAppData !== '') {
            $patterns[] = $localAppData.'\\Microsoft\\WinGet\\Links\\'.$exe;
            $patterns[] = $localAppData.'\\Microsoft\\WinGet\\Packages\\*\\*\\Library\\bin\\'.$exe;
        }

        foreach ([$programFiles, $programFilesX86] as $base) {
            if ($base === '') {
                continue;
            }

            $patterns[] = $base.'\\poppler\\Library\\bin\\'.$exe;
            $patterns[] = $base.'\\poppler\\bin\\'.$exe;
            $patterns[] = $base.'\\OCRmyPDF\\'.$exe;
        }

        foreach ($patterns as $pattern) {
            if (!str_contains($pattern, '*')) {
                if (is_file($pattern) && is_readable($pattern)) {
                    return $pattern;
                }
                continue;
            }

            $matches = glob($pattern) ?: [];
            if ($matches === []) {
                continue;
            }

            rsort($matches, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($matches as $match) {
                if (is_file($match) && is_readable($match)) {
                    return $match;
                }
            }
        }

        return null;
    }

    private function isSummaryQuestion(string $question): bool
    {
        foreach ([
            'contenu',
            'resume',
            'resumer',
            'résumé',
            'résumer',
            'de quoi',
            'pdf',
            'document',
            'sujet',
            'parle',
            'lire',
            'lis',
            'lise',
            'lecture',
            'explique',
            'expliquer',
        ] as $token) {
            if (str_contains($question, $token)) {
                return true;
            }
        }

        return false;
    }

    private function extractKeywords(string $question): array
    {
        $tokens = preg_split('/[^a-z0-9]+/i', $question) ?: [];
        $stopWords = [
            'le', 'la', 'les', 'de', 'des', 'du', 'un', 'une', 'et', 'ou', 'au', 'aux',
            'dans', 'sur', 'pour', 'avec', 'sans', 'que', 'qui', 'quoi', 'est', 'sont',
            'cette', 'ce', 'ces', 'mon', 'ma', 'mes', 'ton', 'ta', 'tes', 'leur', 'leurs',
            'je', 'tu', 'il', 'elle', 'nous', 'vous', 'ils', 'elles', 'estce', 'cest',
        ];

        $keywords = [];
        foreach ($tokens as $token) {
            $token = mb_strtolower(trim($token));
            if ($token === '' || mb_strlen($token) < 3 || in_array($token, $stopWords, true)) {
                continue;
            }
            $keywords[] = $token;
        }

        return array_values(array_unique($keywords));
    }

    private function isRephraseRequest(string $question): bool
    {
        foreach (['corrige', 'corriger', 'reformule', 'reformuler', 'corrrection', 'correction'] as $token) {
            if (str_contains($question, $token)) {
                return true;
            }
        }

        return false;
    }

    private function buildRephraseAnswer(string $question): string
    {
        $clean = trim($this->sanitizeUtf8($question));
        if ($clean === '') {
            return "Pouvez-vous repeter votre question ?";
        }

        $clean = preg_replace('/\bde\s+de\b/i', 'de', $clean) ?? $clean;
        $clean = preg_replace('/\bparti\b/i', 'partie', $clean) ?? $clean;
        $clean = preg_replace('/\bdf\b/i', 'PDF', $clean) ?? $clean;
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;
        $clean = rtrim($clean, " \t\n\r\0\x0B.?!");

        return "Question reformulee: ".$clean." ?";
    }

    private function sanitizeUtf8(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if ($clean === false) {
            $clean = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $clean) ?? $clean;
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;

        return trim($clean);
    }

    private function buildReadableExcerpt(string $text): string
    {
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
        $selected = [];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '' || $this->isMostlyGibberish($sentence)) {
                continue;
            }

            $selected[] = $sentence;
            if (count($selected) >= 3) {
                break;
            }
        }

        if ($selected !== []) {
            return mb_substr(implode(' ', $selected), 0, 650);
        }

        return mb_substr($text, 0, 650);
    }

    private function isUsefulExtractedText(string $text): bool
    {
        $length = mb_strlen($text);
        if ($length < 40) {
            return false;
        }

        preg_match_all('/[\p{L}\p{N}]/u', $text, $letters);
        $alnumCount = count($letters[0] ?? []);
        $ratio = $length > 0 ? ($alnumCount / $length) : 0;

        return $ratio >= 0.30;
    }

    private function isMostlyGibberish(string $text): bool
    {
        $tokens = preg_split('/\s+/u', trim($text)) ?: [];
        if ($tokens === []) {
            return true;
        }

        $wordCount = 0;
        $readableWordCount = 0;
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            $wordCount++;
            if (preg_match('/^[\p{L}]{3,}$/u', $token)) {
                $readableWordCount++;
            }
        }

        if ($wordCount === 0) {
            return true;
        }

        $readableRatio = $readableWordCount / $wordCount;

        $symbolOnly = preg_match_all('/[^\p{L}\p{N}\s.,;:!?()\'"-]/u', $text, $m);
        $symbolRatio = mb_strlen($text) > 0 ? (($symbolOnly ?: 0) / mb_strlen($text)) : 1;

        return $readableRatio < 0.30 || $symbolRatio > 0.12;
    }
}

