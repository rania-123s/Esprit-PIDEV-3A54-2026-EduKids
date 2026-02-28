<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LibreTranslateService
{
    private const SUPPORTED_LANGUAGES = ['en', 'fr', 'ar'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl
    ) {
    }

    public function translate(string $text, string $source, string $target): string
    {
        $normalizedSource = strtolower(trim($source));
        if ($normalizedSource === '') {
            $normalizedSource = 'auto';
        }

        if ($normalizedSource !== 'auto' && !in_array($normalizedSource, self::SUPPORTED_LANGUAGES, true)) {
            throw new \InvalidArgumentException('Invalid source language.');
        }

        if (!in_array($target, self::SUPPORTED_LANGUAGES, true)) {
            throw new \InvalidArgumentException('Invalid target language.');
        }

        if ($normalizedSource !== 'auto' && $normalizedSource === $target) {
            return $text;
        }

        $endpoint = $this->resolveTranslateEndpoint();

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'q' => $text,
                    'source' => $normalizedSource,
                    'target' => $target,
                    'format' => 'text',
                ],
                'timeout' => 10,
            ]);
            $statusCode = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface $exception) {
            throw new \RuntimeException('Translation service is unreachable.', previous: $exception);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Invalid response from translation service.', previous: $exception);
        }

        if ($statusCode >= 400) {
            $error = is_array($payload) ? (string) ($payload['error'] ?? '') : '';
            $message = $error !== '' ? $error : 'Translation service returned an error.';

            throw new \RuntimeException($message);
        }

        $translatedText = is_array($payload) ? trim((string) ($payload['translatedText'] ?? '')) : '';
        if ($translatedText === '') {
            throw new \RuntimeException('Empty translation response.');
        }

        return $translatedText;
    }

    public function detectLanguage(string $text): string
    {
        $endpoint = $this->resolveDetectEndpoint();
        $normalizedText = trim($text);
        if ($normalizedText === '') {
            return 'unknown';
        }

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => ['q' => $normalizedText],
                'timeout' => 4,
            ]);
            $statusCode = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface) {
            return $this->detectLanguageHeuristic($normalizedText);
        } catch (\Throwable) {
            return $this->detectLanguageHeuristic($normalizedText);
        }

        if ($statusCode >= 400) {
            return $this->detectLanguageHeuristic($normalizedText);
        }

        $detected = $this->extractDetectedLanguage($payload);
        if ($detected !== null) {
            return $detected;
        }

        return $this->detectLanguageHeuristic($normalizedText);
    }

    private function resolveTranslateEndpoint(): string
    {
        $base = rtrim(trim($this->baseUrl), '/');
        if ($base === '') {
            throw new \RuntimeException('LIBRETRANSLATE_URL is not configured.');
        }

        return str_ends_with($base, '/translate')
            ? $base
            : $base . '/translate';
    }

    private function resolveDetectEndpoint(): string
    {
        $base = rtrim(trim($this->baseUrl), '/');
        if ($base === '') {
            throw new \RuntimeException('LIBRETRANSLATE_URL is not configured.');
        }

        if (str_ends_with($base, '/detect')) {
            return $base;
        }

        if (str_ends_with($base, '/translate')) {
            return substr($base, 0, -strlen('/translate')) . '/detect';
        }

        return $base . '/detect';
    }

    private function extractDetectedLanguage(mixed $payload): ?string
    {
        if (!is_array($payload) || $payload === []) {
            return null;
        }

        $candidate = $payload[0] ?? null;
        if (!is_array($candidate)) {
            return null;
        }

        $language = strtolower(trim((string) ($candidate['language'] ?? '')));
        if (!in_array($language, self::SUPPORTED_LANGUAGES, true)) {
            return null;
        }

        return $language;
    }

    private function detectLanguageHeuristic(string $text): string
    {
        if ($text === '') {
            return 'unknown';
        }

        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text) === 1) {
            return 'ar';
        }

        $normalized = mb_strtolower($text);
        $frenchHints = [
            ' le ', ' la ', ' les ', ' des ', ' une ', ' un ', ' est ', ' et ', ' je ', ' tu ', ' vous ',
            ' pour ', ' avec ', ' sur ', ' dans ', ' merci ', 'bonjour', 'salut', 'ça', 'être',
        ];

        foreach ($frenchHints as $hint) {
            if (str_contains($normalized, $hint)) {
                return 'fr';
            }
        }

        if (preg_match('/[àâçéèêëîïôûùüÿœ]/u', $normalized) === 1) {
            return 'fr';
        }

        if (preg_match('/[a-z]/', $normalized) === 1) {
            return 'en';
        }

        return 'unknown';
    }
}
