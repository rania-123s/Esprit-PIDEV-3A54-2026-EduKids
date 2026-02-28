<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiService
{
    private const ALLOWED_LANGUAGES = ['fr', 'en', 'ar'];
    private const ALLOWED_SOURCE_LANGUAGES = ['auto', 'fr', 'en', 'ar'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AttachmentSummaryAiService $attachmentSummaryAiService,
        private readonly LibreTranslateService $libreTranslateService,
        #[Autowire('%env(string:OPENROUTER_API_URL)%')]
        private readonly string $apiUrl,
        #[Autowire('%env(string:OPENROUTER_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(string:OPENROUTER_MODEL)%')]
        private readonly string $model
    ) {
    }

    /**
     * @param array<int, array{id: string|int, senderName?: string, content: string}> $messages
     */
    public function analyzePdfAndTranslateConversation(
        string $documentText,
        string $fileName,
        array $messages,
        string $targetLanguage = 'fr'
    ): string {
        $target = $this->normalizeLanguage($targetLanguage, 'fr');
        $safeMessages = $this->normalizeMessages($messages);
        $safeDocumentText = mb_substr(trim($documentText), 0, 16000);

        if (
            !$this->hasUsableApiKey(trim($this->apiKey))
            || trim($this->apiUrl) === ''
            || trim($this->model) === ''
        ) {
            return $this->buildLocalFallback($safeDocumentText, $fileName, $safeMessages, $target);
        }

        $prompt = $this->buildPrompt($safeDocumentText, $fileName, $safeMessages, $target);

        try {
            $response = $this->httpClient->request('POST', trim($this->apiUrl), [
                'headers' => $this->buildHeaders(trim($this->apiUrl), trim($this->apiKey)),
                'json' => [
                    'model' => trim($this->model),
                    'temperature' => 0.2,
                    'max_tokens' => 1000,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tu es un assistant fiable. Reponds en texte brut, sans markdown.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ],
                'timeout' => 60,
            ]);
        } catch (\Throwable) {
            return $this->buildLocalFallback($safeDocumentText, $fileName, $safeMessages, $target);
        }

        $statusCode = $response->getStatusCode();
        $payload = $response->toArray(false);
        if ($statusCode >= 400) {
            return $this->buildLocalFallback($safeDocumentText, $fileName, $safeMessages, $target);
        }

        $text = trim($this->extractOutputText($payload));
        if ($text === '') {
            return $this->buildLocalFallback($safeDocumentText, $fileName, $safeMessages, $target);
        }

        return $text;
    }

    public function analyzePdfWithOpenRouter(
        string $documentText,
        string $fileName,
        string $modelOverride = 'queen'
    ): string {
        $safeDocumentText = mb_substr(trim($documentText), 0, 20000);
        $prompt = $this->buildPdfOnlyPrompt($safeDocumentText, $fileName);

        try {
            $text = $this->requestOpenRouterText(
                $prompt,
                'Tu produis des resumes fiables, factuels, en francais simple et clair. Reponds en texte brut.',
                700,
                0.2,
                $modelOverride,
                60
            );

            return $this->normalizeFiveLineSummaryText($text, $safeDocumentText);
        } catch (\Throwable) {
            return $this->buildFiveLineSummaryFallback($safeDocumentText);
        }
    }

    public function translateTextWithOpenRouter(
        string $text,
        string $sourceLanguage,
        string $targetLanguage,
        string $modelOverride = 'queen'
    ): string {
        $normalizedText = trim($text);
        if ($normalizedText === '') {
            throw new \InvalidArgumentException('Text cannot be empty.');
        }

        $target = strtolower(trim($targetLanguage));
        if (!in_array($target, self::ALLOWED_LANGUAGES, true)) {
            throw new \InvalidArgumentException('Invalid target language.');
        }

        $source = strtolower(trim($sourceLanguage));
        if ($source === '') {
            $source = 'auto';
        }

        if (!in_array($source, self::ALLOWED_SOURCE_LANGUAGES, true)) {
            throw new \InvalidArgumentException('Invalid source language.');
        }

        if ($source !== 'auto' && $source === $target) {
            return $normalizedText;
        }

        $sourceLabel = $source === 'auto' ? 'auto-detect' : $source;
        $prompt = <<<PROMPT
Translate the text from {$sourceLabel} to {$target}.
Return only the translated text.
Keep meaning, tone, punctuation and names.

Text:
{$normalizedText}
PROMPT;

        return $this->requestOpenRouterText(
            $prompt,
            'You are a translation engine. Output only translated text without comments.',
            900,
            0,
            $modelOverride,
            45
        );
    }

    private function normalizeLanguage(string $language, string $fallback): string
    {
        $normalized = strtolower(trim($language));
        if (!in_array($normalized, self::ALLOWED_LANGUAGES, true)) {
            return $fallback;
        }

        return $normalized;
    }

    /**
     * @param array<int, array{id: string|int, senderName?: string, content: string}> $messages
     * @return array<int, array{id: string, senderName: string, content: string}>
     */
    private function normalizeMessages(array $messages): array
    {
        $normalized = [];
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $content = trim((string) ($message['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $normalized[] = [
                'id' => trim((string) ($message['id'] ?? '')),
                'senderName' => trim((string) ($message['senderName'] ?? 'Utilisateur')) ?: 'Utilisateur',
                'content' => mb_substr($content, 0, 600),
            ];

            if (count($normalized) >= 25) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @param array<int, array{id: string, senderName: string, content: string}> $messages
     */
    private function buildPrompt(string $documentText, string $fileName, array $messages, string $targetLanguage): string
    {
        $safeFileName = trim($fileName) !== '' ? trim($fileName) : 'document.pdf';
        $messagesJson = json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($messagesJson)) {
            $messagesJson = '[]';
        }

        return <<<PROMPT
Tu dois analyser un PDF et traduire les messages d une conversation.
N invente aucune information.
Si une information manque, ecris "Information non disponible".
Reponds en texte brut avec cette structure exacte:

1) Analyse du document PDF
- Resume court (5 a 8 lignes)
- Points cles (5 puces max)
- Actions recommandees

2) Traduction des messages de conversation vers {$targetLanguage}
- Une ligne par message au format:
[id:<id>][sender:<nom>] original => traduction

Fichier: {$safeFileName}
Texte extrait du PDF:
{$documentText}

Messages de conversation (JSON):
{$messagesJson}
PROMPT;
    }

    private function buildPdfOnlyPrompt(string $documentText, string $fileName): string
    {
        $safeName = trim($fileName) !== '' ? trim($fileName) : 'document.pdf';

        return <<<PROMPT
Tu dois analyser un document PDF.
N invente aucune information.
Si une information n est pas presente, indique "Non precise".
Reponds en texte brut avec cette structure exacte et rien d autre:

1) Resume en 5 lignes
- <ligne 1>
- <ligne 2>
- <ligne 3>
- <ligne 4>
- <ligne 5>

Fichier: {$safeName}
Texte extrait du PDF:
{$documentText}
PROMPT;
    }

    private function normalizeFiveLineSummaryText(string $summaryText, string $documentText): string
    {
        $lines = preg_split('/\R+/u', trim($summaryText)) ?: [];
        $normalizedLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $lower = mb_strtolower($line);
            if (
                str_starts_with($lower, '2)')
                || str_starts_with($lower, '3)')
                || str_contains($lower, 'points cles')
                || str_contains($lower, 'actions / infos importantes')
                || str_contains($lower, 'traduction des messages')
            ) {
                break;
            }

            $line = preg_replace('/^\s*(?:[-*•]\s*|\d+\)\s*|\d+\.\s*)/u', '', $line) ?? $line;
            $line = trim($line);
            if ($line === '' || str_starts_with(mb_strtolower($line), 'resume en 5 lignes')) {
                continue;
            }

            $normalizedLines[] = $line;
            if (count($normalizedLines) >= 5) {
                break;
            }
        }

        if (count($normalizedLines) < 5) {
            $missing = 5 - count($normalizedLines);
            $extraLines = $this->extractSummaryLinesFromText($documentText, $missing);
            foreach ($extraLines as $extraLine) {
                if (count($normalizedLines) >= 5) {
                    break;
                }
                $normalizedLines[] = $extraLine;
            }
        }

        while (count($normalizedLines) < 5) {
            $normalizedLines[] = 'Non precise.';
        }

        return "1) Resume en 5 lignes\n- " . implode("\n- ", array_slice($normalizedLines, 0, 5));
    }

    private function buildFiveLineSummaryFallback(string $documentText): string
    {
        $lines = $this->extractSummaryLinesFromText($documentText, 5);
        while (count($lines) < 5) {
            $lines[] = 'Non precise.';
        }

        return "1) Resume en 5 lignes\n- " . implode("\n- ", array_slice($lines, 0, 5));
    }

    /**
     * @return string[]
     */
    private function extractSummaryLinesFromText(string $documentText, int $maxLines): array
    {
        if ($maxLines <= 0) {
            return [];
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($documentText)) ?? trim($documentText);
        if ($normalized === '') {
            return [];
        }

        $sentences = preg_split('/(?<=[\.\!\?])\s+/u', $normalized) ?: [];
        $lines = [];
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }

            $lines[] = mb_substr($sentence, 0, 220);
            if (count($lines) >= $maxLines) {
                return $lines;
            }
        }

        if ($lines === []) {
            $chunks = preg_split('/\R+/u', trim($documentText)) ?: [];
            foreach ($chunks as $chunk) {
                $chunk = trim($chunk);
                if ($chunk === '') {
                    continue;
                }

                $lines[] = mb_substr($chunk, 0, 220);
                if (count($lines) >= $maxLines) {
                    break;
                }
            }
        }

        if ($lines === []) {
            $lines[] = mb_substr($normalized, 0, 220);
        }

        return array_slice($lines, 0, $maxLines);
    }

    private function requestOpenRouterText(
        string $prompt,
        string $systemPrompt,
        int $maxTokens,
        float $temperature,
        string $modelOverride,
        int $timeoutSeconds
    ): string {
        $apiKey = trim($this->apiKey);
        $apiUrl = trim($this->apiUrl);
        if (!$this->hasUsableApiKey($apiKey) || $apiUrl === '') {
            throw new \RuntimeException('OpenRouter is not configured.');
        }

        $lastError = 'OpenRouter request failed.';
        foreach ($this->resolveModelCandidates($modelOverride) as $model) {
            try {
                $response = $this->httpClient->request('POST', $apiUrl, [
                    'headers' => $this->buildHeaders($apiUrl, $apiKey),
                    'json' => [
                        'model' => $model,
                        'temperature' => $temperature,
                        'max_tokens' => $maxTokens,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => $systemPrompt,
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt,
                            ],
                        ],
                    ],
                    'timeout' => $timeoutSeconds,
                ]);
            } catch (\Throwable $exception) {
                $lastError = 'OpenRouter request failed for model ' . $model . '.';
                continue;
            }

            $statusCode = $response->getStatusCode();
            $payload = $response->toArray(false);
            if ($statusCode >= 400) {
                $apiError = $this->extractApiErrorMessage($payload);
                $lastError = $apiError !== ''
                    ? $apiError
                    : sprintf('OpenRouter returned HTTP %d for model %s.', $statusCode, $model);
                continue;
            }

            $text = trim($this->extractOutputText($payload));
            if ($text !== '') {
                return $text;
            }

            $lastError = 'OpenRouter returned an empty response for model ' . $model . '.';
        }

        throw new \RuntimeException($lastError);
    }

    /**
     * @return string[]
     */
    private function resolveModelCandidates(string $modelOverride): array
    {
        $candidates = [];
        $defaults = [
            trim($modelOverride),
            trim($this->model),
            'openrouter/auto',
            'qwen/qwen-2.5-7b-instruct:free',
        ];

        foreach ($defaults as $candidate) {
            if ($candidate === '' || in_array($candidate, $candidates, true)) {
                continue;
            }

            $candidates[] = $candidate;
        }

        return $candidates;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractApiErrorMessage(array $payload): string
    {
        $error = $payload['error'] ?? null;
        if (!is_array($error)) {
            return '';
        }

        $message = $error['message'] ?? '';
        return is_string($message) ? trim($message) : '';
    }

    private function hasUsableApiKey(string $apiKey): bool
    {
        if ($apiKey === '') {
            return false;
        }

        $upper = strtoupper($apiKey);
        if (str_contains($upper, 'YOUR_') || str_contains($upper, 'REPLACE_') || str_contains($upper, 'CHANGE_')) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(string $apiUrl, string $apiKey): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ];

        if (str_contains(strtolower($apiUrl), 'openrouter.ai')) {
            $headers['HTTP-Referer'] = 'http://localhost';
            $headers['X-Title'] = 'EduKids';
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractOutputText(array $payload): string
    {
        $choices = $payload['choices'] ?? null;
        if (!is_array($choices) || !isset($choices[0]) || !is_array($choices[0])) {
            return '';
        }

        $message = $choices[0]['message'] ?? null;
        if (!is_array($message)) {
            return '';
        }

        $content = $message['content'] ?? null;
        if (is_string($content)) {
            return $content;
        }

        if (!is_array($content)) {
            return '';
        }

        $parts = [];
        foreach ($content as $entry) {
            if (is_string($entry) && trim($entry) !== '') {
                $parts[] = trim($entry);
                continue;
            }

            if (!is_array($entry)) {
                continue;
            }

            $text = $entry['text'] ?? null;
            if (is_string($text) && trim($text) !== '') {
                $parts[] = trim($text);
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @param array<int, array{id: string, senderName: string, content: string}> $messages
     */
    private function buildLocalFallback(string $documentText, string $fileName, array $messages, string $targetLanguage): string
    {
        $documentAnalysis = $this->attachmentSummaryAiService->summarizeInFrench($documentText, $fileName);
        $translatedLines = $this->translateMessagesLocally($messages, $targetLanguage);

        return sprintf(
            "1) Analyse du document PDF\n%s\n\n2) Traduction des messages de conversation vers %s\n%s",
            trim($documentAnalysis),
            strtoupper($targetLanguage),
            $translatedLines === [] ? '- Information non disponible' : implode("\n", $translatedLines)
        );
    }

    /**
     * @param array<int, array{id: string, senderName: string, content: string}> $messages
     * @return string[]
     */
    private function translateMessagesLocally(array $messages, string $targetLanguage): array
    {
        $lines = [];
        foreach ($messages as $message) {
            $id = trim((string) ($message['id'] ?? ''));
            $senderName = trim((string) ($message['senderName'] ?? 'Utilisateur')) ?: 'Utilisateur';
            $content = trim((string) ($message['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $sourceLanguage = 'unknown';
            $translated = $content;

            try {
                $detected = $this->libreTranslateService->detectLanguage($content);
                $sourceLanguage = $this->normalizeLanguage($detected, 'unknown');
                if ($sourceLanguage !== 'unknown' && $sourceLanguage !== $targetLanguage) {
                    $translated = $this->libreTranslateService->translate($content, $sourceLanguage, $targetLanguage);
                }
            } catch (\Throwable) {
                $translated = $content;
            }

            $lines[] = sprintf(
                '- [id:%s][source:%s][sender:%s] %s => %s',
                $id !== '' ? $id : '?',
                $sourceLanguage,
                $senderName,
                $content,
                $translated
            );
        }

        return $lines;
    }
}
