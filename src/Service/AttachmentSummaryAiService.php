<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AttachmentSummaryAiService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(ATTACHMENT_SUMMARY_API_URL)%')]
        private readonly string $apiUrl,
        #[Autowire('%env(ATTACHMENT_SUMMARY_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(ATTACHMENT_SUMMARY_MODEL)%')]
        private readonly string $model
    ) {
    }

    public function summarizeInFrench(string $documentText, string $fileName = ''): string
    {
        $apiKey = trim($this->apiKey);
        $apiUrl = trim($this->apiUrl);
        if (!$this->hasUsableApiKey($apiKey) || $apiUrl === '' || trim($this->model) === '') {
            return $this->summarizeLocally($documentText, $fileName);
        }

        $prompt = $this->buildPrompt($documentText, $fileName);
        $usesChatCompletions = $this->usesChatCompletionsApi($apiUrl);

        try {
            $response = $this->httpClient->request('POST', $apiUrl, [
                'headers' => $this->buildRequestHeaders($apiUrl, $apiKey),
                'json' => $this->buildRequestPayload($prompt, $usesChatCompletions),
                'timeout' => 60,
            ]);
        } catch (\Throwable) {
            return $this->summarizeLocally($documentText, $fileName);
        }

        $statusCode = $response->getStatusCode();
        $payload = $response->toArray(false);

        if ($statusCode === 401 || $statusCode === 403) {
            return $this->summarizeLocally($documentText, $fileName);
        }

        if ($statusCode >= 400) {
            return $this->summarizeLocally($documentText, $fileName);
        }

        $summary = trim($this->extractOutputText($payload));
        if ($summary === '') {
            return $this->summarizeLocally($documentText, $fileName);
        }

        return $summary;
    }

    private function usesChatCompletionsApi(string $apiUrl): bool
    {
        return str_contains(strtolower($apiUrl), '/chat/completions');
    }

    /**
     * @return array<string, string>
     */
    private function buildRequestHeaders(string $apiUrl, string $apiKey): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ];

        if (str_contains(strtolower($apiUrl), 'openrouter.ai')) {
            // OpenRouter recommends these headers for app identification.
            $headers['HTTP-Referer'] = 'http://localhost';
            $headers['X-Title'] = 'EduKids';
        }

        return $headers;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequestPayload(string $prompt, bool $usesChatCompletions): array
    {
        if ($usesChatCompletions) {
            return [
                'model' => $this->model,
                'temperature' => 0.2,
                'max_tokens' => 700,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu produis des resumes fiables, factuels, en francais simple et clair.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ];
        }

        return [
            'model' => $this->model,
            'temperature' => 0.2,
            'max_output_tokens' => 700,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => 'Tu produis des resumes fiables, factuels, en francais simple et clair.',
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function hasUsableApiKey(string $apiKey): bool
    {
        if ($apiKey === '') {
            return false;
        }

        $upper = strtoupper($apiKey);
        if (str_contains($upper, 'YOUR_OPENAI_API_KEY_HERE')) {
            return false;
        }

        if (preg_match('/^(YOUR_|REPLACE_|CHANGE_)/', $upper) === 1) {
            return false;
        }

        return true;
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

    /**
     * @param array<string, mixed> $payload
     */
    private function extractApiErrorCode(array $payload): string
    {
        $error = $payload['error'] ?? null;
        if (!is_array($error)) {
            return '';
        }

        $code = $error['code'] ?? '';

        return is_string($code) ? trim($code) : '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractOutputText(array $payload): string
    {
        $choices = $payload['choices'] ?? null;
        if (is_array($choices) && isset($choices[0]) && is_array($choices[0])) {
            $message = $choices[0]['message'] ?? null;
            if (is_array($message)) {
                $content = $message['content'] ?? null;
                if (is_string($content) && trim($content) !== '') {
                    return trim($content);
                }

                if (is_array($content)) {
                    $parts = [];
                    foreach ($content as $entry) {
                        if (is_string($entry) && trim($entry) !== '') {
                            $parts[] = trim($entry);
                            continue;
                        }

                        if (is_array($entry)) {
                            $text = $entry['text'] ?? null;
                            if (is_string($text) && trim($text) !== '') {
                                $parts[] = trim($text);
                            }
                        }
                    }

                    if ($parts !== []) {
                        return trim(implode("\n", $parts));
                    }
                }
            }
        }

        $outputText = $payload['output_text'] ?? null;
        if (is_string($outputText) && trim($outputText) !== '') {
            return $outputText;
        }

        $output = $payload['output'] ?? null;
        if (!is_array($output)) {
            return '';
        }

        $parts = [];
        foreach ($output as $item) {
            if (!is_array($item)) {
                continue;
            }

            $content = $item['content'] ?? null;
            if (!is_array($content)) {
                continue;
            }

            foreach ($content as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $text = $entry['text'] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    $parts[] = $text;
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    private function buildPrompt(string $documentText, string $fileName): string
    {
        $safeName = trim($fileName) !== '' ? trim($fileName) : 'document';
        $content = mb_substr(trim($documentText), 0, 20000);

        return <<<PROMPT
Fichier: {$safeName}

Consigne:
- Resume ce document en francais.
- Sois factuel: n invente aucune information absente du texte.
- Si une information n est pas presente, indique "Non precise".

Format de sortie strict:
1) Resume en 5 lignes
2) Points cles (5 a 8 puces)
3) Actions / infos importantes (si applicable)

Document:
{$content}
PROMPT;
    }

    private function summarizeLocally(string $documentText, string $fileName): string
    {
        $safeName = trim($fileName) !== '' ? trim($fileName) : 'document';
        $normalized = preg_replace('/\s+/u', ' ', trim($documentText)) ?? trim($documentText);
        if ($normalized === '') {
            return "1) Resume en 5 lignes\n- Non precise.\n\n2) Points cles (5 a 8 puces)\n- Non precise.\n\n3) Actions / infos importantes (si applicable)\n- Non precise.";
        }

        $sentences = preg_split('/(?<=[\.\!\?])\s+/u', $normalized) ?: [];
        $sentences = array_values(array_filter(array_map(
            static fn (string $s): string => trim($s),
            $sentences
        ), static fn (string $s): bool => $s !== ''));

        if ($sentences === []) {
            $chunks = preg_split('/\n+/u', trim($documentText)) ?: [];
            $sentences = array_values(array_filter(array_map(
                static fn (string $s): string => trim($s),
                $chunks
            ), static fn (string $s): bool => $s !== ''));
        }

        $summaryLines = array_slice($sentences, 0, 5);
        while (count($summaryLines) < 5) {
            $summaryLines[] = 'Non precise.';
        }

        $keyPoints = [];
        foreach ($sentences as $sentence) {
            if (mb_strlen($sentence) < 25) {
                continue;
            }

            $keyPoints[] = $sentence;
            if (count($keyPoints) >= 8) {
                break;
            }
        }

        if ($keyPoints === []) {
            $keyPoints = array_slice($summaryLines, 0, 5);
        }

        $actions = [];
        foreach ($sentences as $sentence) {
            if (preg_match('/\b(doit|devrait|recommande|important|deadline|date limite|action|etape)\b/iu', $sentence) === 1) {
                $actions[] = $sentence;
                if (count($actions) >= 5) {
                    break;
                }
            }
        }

        if ($actions === []) {
            $actions[] = 'Aucune action explicite detectee dans le document.';
        }

        return sprintf(
            "Fichier: %s\n\n1) Resume en 5 lignes\n- %s\n\n2) Points cles (5 a 8 puces)\n- %s\n\n3) Actions / infos importantes (si applicable)\n- %s",
            $safeName,
            implode("\n- ", $summaryLines),
            implode("\n- ", array_slice($keyPoints, 0, 8)),
            implode("\n- ", $actions)
        );
    }

}
