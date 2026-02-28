<?php

namespace App\Controller;

use App\Service\AiService;
use App\Service\LibreTranslateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TranslateController extends AbstractController
{
    private const MAX_TEXT_LENGTH = 5000;

    private const ALLOWED_LANGUAGES = ['en', 'fr', 'ar'];
    private const ALLOWED_SOURCE_LANGUAGES = ['auto', 'en', 'fr', 'ar'];

    public function __construct(
        private readonly LibreTranslateService $libreTranslateService,
        private readonly AiService $aiService
    ) {
    }

    #[Route('/api/translate', name: 'api_translate', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function translate(Request $request): JsonResponse
    {
        $this->assertCsrf($request);

        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return $this->json(['error' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $text = trim((string) ($payload['text'] ?? ''));
        $target = strtolower(trim((string) ($payload['target'] ?? '')));
        $source = strtolower(trim((string) ($payload['source'] ?? 'auto')));

        if ($text === '') {
            return $this->json(['error' => 'Text cannot be empty.'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            return $this->json([
                'error' => sprintf('Text exceeds max length (%d characters).', self::MAX_TEXT_LENGTH),
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($target, self::ALLOWED_LANGUAGES, true)) {
            return $this->json(['error' => 'Invalid target language.'], Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($source, self::ALLOWED_SOURCE_LANGUAGES, true)) {
            return $this->json(['error' => 'Invalid source language.'], Response::HTTP_BAD_REQUEST);
        }

        if ($source !== 'auto' && $source === $target) {
            return $this->json(['error' => 'Source and target languages must differ.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $translatedText = $this->libreTranslateService->translate($text, $source, $target);
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'error' => mb_substr(trim($exception->getMessage()), 0, 240),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException) {
            try {
                $translatedText = $this->aiService->translateTextWithOpenRouter($text, $source, $target, 'queen');
            } catch (\InvalidArgumentException $fallbackException) {
                return $this->json([
                    'error' => mb_substr(trim($fallbackException->getMessage()), 0, 240),
                ], Response::HTTP_BAD_REQUEST);
            } catch (\RuntimeException $fallbackException) {
                return $this->json([
                    'error' => $this->formatServiceErrorMessage($fallbackException),
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }
        }

        return $this->json(['translatedText' => $translatedText]);
    }

    private function formatServiceErrorMessage(\RuntimeException $exception): string
    {
        $message = trim($exception->getMessage());
        if ($message === '') {
            return 'Translation service unavailable.';
        }

        return mb_substr($message, 0, 240);
    }

    private function assertCsrf(Request $request): void
    {
        $token = $request->headers->get('X-CSRF-TOKEN') ?? '';
        if (!$this->isCsrfTokenValid('chat_action', $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
