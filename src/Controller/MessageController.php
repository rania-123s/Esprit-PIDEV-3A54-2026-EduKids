<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\MessageAttachment;
use App\Entity\MessageAttachmentSummary;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ConversationParticipantRepository;
use App\Repository\MessageAttachmentRepository;
use App\Repository\MessageAttachmentSummaryRepository;
use App\Repository\MessageRepository;
use App\Security\Voter\ConversationVoter;
use App\Service\AiService;
use App\Service\AttachmentTextExtractor;
use App\Service\ChatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chat')]
class MessageController extends AbstractController
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly MessageAttachmentRepository $messageAttachmentRepository,
        private readonly ConversationParticipantRepository $conversationParticipantRepository,
        private readonly MessageAttachmentSummaryRepository $messageAttachmentSummaryRepository,
        private readonly AttachmentTextExtractor $attachmentTextExtractor,
        private readonly AiService $aiService,
        private readonly EntityManagerInterface $em,
        private readonly ChatService $chatService,
        #[Autowire('%chat_audio_max_size_bytes%')]
        private readonly int $chatAudioMaxSizeBytes,
        #[Autowire('%chat_audio_max_duration_seconds%')]
        private readonly int $chatAudioMaxDurationSeconds,
        #[Autowire('%chat_audio_allowed_mime_types%')]
        private readonly array $chatAudioAllowedMimeTypes
    ) {
    }

    #[Route('/{id}/messages', name: 'chat_messages', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function listMessages(Conversation $conversation, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);

        $loadAll = filter_var($request->query->get('all', '1'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($loadAll === null || $loadAll) {
            $messages = $this->messageRepository->findAllForConversation($conversation);
            $data = array_map(fn (Message $message): array => $this->chatService->serializeMessage($message), $messages);

            return $this->json([
                'items' => $data,
                'all' => true,
                'count' => count($data),
            ]);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = max(1, min((int) $request->query->get('perPage', 30), 100));

        $messages = $this->messageRepository->findPaginatedForConversation($conversation, $page, $perPage);
        $data = array_map(fn (Message $message): array => $this->chatService->serializeMessage($message), $messages);

        return $this->json([
            'items' => $data,
            'page' => $page,
            'perPage' => $perPage,
            'all' => false,
        ]);
    }

    #[Route('/{id}/messages', name: 'chat_message_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Route('/{id}/message', name: 'chat_message_create_legacy', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function sendMessage(Conversation $conversation, Request $request): JsonResponse
    {
        $this->assertCsrf($request);
        $this->denyAccessUnlessGranted(ConversationVoter::MESSAGE, $conversation);
        /** @var User $user */
        $user = $this->getUser();

        $content = trim((string) $request->request->get('content', ''));
        $attachments = $this->extractAttachments($request);

        if ($content === '' && $attachments === []) {
            return $this->json(['error' => 'Empty message.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $message = $this->chatService->createMessage($conversation, $user, $content, $attachments);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException) {
            return $this->json(['error' => 'Unable to upload attachment.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->chatService->serializeMessage($message), Response::HTTP_CREATED);
    }

    #[Route('/{id}/messages/audio', name: 'chat_message_audio_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function sendAudioMessage(Conversation $conversation, Request $request): JsonResponse
    {
        $this->assertCsrf($request);
        $this->denyAccessUnlessGranted(ConversationVoter::MESSAGE, $conversation);
        /** @var User $user */
        $user = $this->getUser();

        $audioFile = $this->extractAudioFile($request);
        if (!$audioFile instanceof UploadedFile) {
            return $this->json(['error' => 'Audio file is required.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$audioFile->isValid()) {
            return $this->json(['error' => 'Invalid audio upload.'], Response::HTTP_BAD_REQUEST);
        }

        $size = (int) ($audioFile->getSize() ?? 0);
        if ($size <= 0) {
            return $this->json(['error' => 'Uploaded audio is empty.'], Response::HTTP_BAD_REQUEST);
        }

        if ($size > $this->chatAudioMaxSizeBytes) {
            return $this->json([
                'error' => sprintf(
                    'Audio exceeds maximum allowed size (%d MB).',
                    (int) ceil($this->chatAudioMaxSizeBytes / 1024 / 1024)
                ),
            ], Response::HTTP_BAD_REQUEST);
        }

        $mimeType = strtolower(trim((string) $audioFile->getMimeType()));
        if (!$this->isAllowedAudioMimeType($mimeType)) {
            return $this->json([
                'error' => sprintf('Audio type "%s" is not allowed.', $mimeType !== '' ? $mimeType : 'unknown'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $durationSeconds = $this->normalizeAudioDurationSeconds($request);
        if ($durationSeconds !== null && $durationSeconds > $this->chatAudioMaxDurationSeconds) {
            return $this->json([
                'error' => sprintf('Audio too long. Maximum duration is %d seconds.', $this->chatAudioMaxDurationSeconds),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $message = $this->chatService->createAudioMessage($conversation, $user, $audioFile, $durationSeconds);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException) {
            return $this->json(['error' => 'Unable to upload audio message.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $serialized = $this->chatService->serializeMessage($message);
        $firstAttachment = $serialized['attachments'][0] ?? null;

        return $this->json([
            'messageId' => $message->getId(),
            'audioUrl' => is_array($firstAttachment) ? ($firstAttachment['url'] ?? null) : null,
            'duration' => is_array($firstAttachment) ? ($firstAttachment['duration'] ?? $durationSeconds) : $durationSeconds,
            'senderName' => $serialized['senderName'] ?? 'Utilisateur',
            'createdAt' => $serialized['createdAt'] ?? null,
            'conversationId' => $conversation->getId(),
            'message' => $serialized,
        ], Response::HTTP_CREATED);
    }

    #[Route('/messages/{id}', name: 'chat_message_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function updateMessage(Message $message, Request $request): JsonResponse
    {
        $this->assertCsrf($request);
        $this->denyAccessUnlessGranted(ConversationVoter::MESSAGE, $message->getConversation());
        /** @var User $user */
        $user = $this->getUser();

        $payload = $request->toArray();
        $content = trim((string) ($payload['content'] ?? ''));
        if ($content === '') {
            return $this->json(['error' => 'Empty content.'], Response::HTTP_BAD_REQUEST);
        }

        $this->chatService->updateMessage($message, $user, $content);

        return $this->json($this->chatService->serializeMessage($message));
    }

    #[Route('/messages/{id}', name: 'chat_message_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function deleteMessage(Message $message, Request $request): JsonResponse
    {
        $this->assertCsrf($request);
        $this->denyAccessUnlessGranted(ConversationVoter::MESSAGE, $message->getConversation());
        /** @var User $user */
        $user = $this->getUser();

        $this->chatService->deleteMessage($message, $user);

        return $this->json($this->chatService->serializeMessage($message));
    }

    #[Route('/{id}/images', name: 'chat_conversation_images', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function recentImages(Conversation $conversation): JsonResponse
    {
        $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);

        $images = $this->messageRepository->findRecentImages($conversation, 24);
        $data = array_map(fn (Message $m): array => $this->chatService->serializeMessage($m), $images);

        return $this->json($data);
    }

    #[Route('/attachment/{attachmentId}', name: 'chat_attachment_download', methods: ['GET'], requirements: ['attachmentId' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function downloadAttachment(int $attachmentId, Request $request): Response
    {
        $attachment = $this->messageAttachmentRepository->find($attachmentId);
        if (!$attachment instanceof MessageAttachment) {
            throw $this->createNotFoundException('Attachment not found.');
        }

        $message = $attachment->getMessage();
        $conversation = $message?->getConversation();
        if (!$message instanceof Message || !$conversation instanceof Conversation) {
            throw $this->createNotFoundException('Attachment message not found.');
        }

        $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);

        $absolutePath = $this->chatService->resolveAttachmentAbsolutePath($attachment);
        if (!is_file($absolutePath)) {
            throw $this->createNotFoundException('Attachment file missing.');
        }

        $download = $request->query->getBoolean('download');
        $disposition = $download || !$attachment->isImage()
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE;

        $response = new BinaryFileResponse($absolutePath);
        $response->setPrivate();
        $response->headers->set('Content-Type', $attachment->getMimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition($disposition, $attachment->getOriginalName());

        return $response;
    }

    #[Route('/audio/{attachmentId}', name: 'chat_audio_stream', methods: ['GET'], requirements: ['attachmentId' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function streamAudioAttachment(int $attachmentId, Request $request): Response
    {
        $attachment = $this->messageAttachmentRepository->find($attachmentId);
        if (!$attachment instanceof MessageAttachment || !$this->isAudioAttachment($attachment)) {
            throw $this->createNotFoundException('Audio attachment not found.');
        }

        $message = $attachment->getMessage();
        $conversation = $message?->getConversation();
        if (!$message instanceof Message || !$conversation instanceof Conversation) {
            throw $this->createNotFoundException('Audio message not found.');
        }

        $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);

        $absolutePath = $this->chatService->resolveAttachmentAbsolutePath($attachment);
        if (!is_file($absolutePath)) {
            throw $this->createNotFoundException('Audio file missing.');
        }

        $download = $request->query->getBoolean('download');
        $disposition = $download
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE;

        $response = new BinaryFileResponse($absolutePath);
        $response->setPrivate();
        $response->headers->set('Content-Type', $attachment->getMimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition($disposition, $attachment->getOriginalName());

        return $response;
    }

    #[Route('/attachments/{attachmentId}/summarize', name: 'chat_attachment_summarize', methods: ['POST'], requirements: ['attachmentId' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function summarizeAttachment(int $attachmentId, Request $request): JsonResponse
    {
        $this->assertCsrf($request);
        /** @var User $user */
        $user = $this->getUser();

        $attachment = $this->messageAttachmentRepository->find($attachmentId);
        if (!$attachment instanceof MessageAttachment) {
            throw $this->createNotFoundException('Attachment not found.');
        }

        $message = $attachment->getMessage();
        $conversation = $message?->getConversation();
        if (!$message instanceof Message || !$conversation instanceof Conversation) {
            throw $this->createNotFoundException('Attachment message not found.');
        }

        $membership = $this->conversationParticipantRepository->findActiveForConversationAndUser($conversation, $user);
        if ($membership === null) {
            throw $this->createAccessDeniedException('Access denied to this conversation attachment.');
        }

        $summary = $this->messageAttachmentSummaryRepository->findOneByAttachmentAndUser($attachment, $user);
        if (
            $summary instanceof MessageAttachmentSummary
            && $summary->isDone()
            && $summary->getSummaryText() !== ''
            && !$this->isCombinedAiSummary($summary->getSummaryText())
            && !$this->isLegacyDetailedPdfSummary($summary->getSummaryText())
        ) {
            return $this->json([
                'summaryText' => $summary->getSummaryText(),
                'cached' => true,
            ]);
        }

        if (!$summary instanceof MessageAttachmentSummary) {
            $summary = (new MessageAttachmentSummary())
                ->setAttachment($attachment)
                ->setUser($user);
            $this->em->persist($summary);
        }

        $summary
            ->setStatus(MessageAttachmentSummary::STATUS_PENDING)
            ->setSummaryText('')
            ->setErrorMessage(null);
        $this->em->flush();

        try {
            $absolutePath = $this->chatService->resolveAttachmentAbsolutePath($attachment);
            $documentText = $this->attachmentTextExtractor->extractFromAttachment($attachment, $absolutePath);
            $summaryText = $this->aiService->analyzePdfWithOpenRouter(
                $documentText,
                $attachment->getOriginalName(),
                'queen'
            );

            $summary
                ->setSummaryText($summaryText)
                ->setStatus(MessageAttachmentSummary::STATUS_DONE)
                ->setErrorMessage(null);
            $this->em->flush();
        } catch (\Throwable $exception) {
            $summary
                ->setStatus(MessageAttachmentSummary::STATUS_ERROR)
                ->setErrorMessage(mb_substr(trim($exception->getMessage()), 0, 500));
            $this->em->flush();

            return $this->json([
                'error' => $this->getAttachmentSummaryErrorMessage($exception),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'summaryText' => $summary->getSummaryText(),
            'cached' => false,
        ]);
    }

    /**
     * @return UploadedFile[]
     */
    private function extractAttachments(Request $request): array
    {
        $attachments = [];

        $legacyFile = $request->files->get('file');
        if ($legacyFile instanceof UploadedFile) {
            $attachments[] = $legacyFile;
        }

        $rawAttachments = $request->files->get('attachments');
        if ($rawAttachments instanceof UploadedFile) {
            $attachments[] = $rawAttachments;
        } elseif (is_array($rawAttachments)) {
            foreach ($rawAttachments as $file) {
                if ($file instanceof UploadedFile) {
                    $attachments[] = $file;
                }
            }
        }

        return $attachments;
    }

    private function extractAudioFile(Request $request): ?UploadedFile
    {
        $audio = $request->files->get('audio');
        if ($audio instanceof UploadedFile) {
            return $audio;
        }

        $legacy = $request->files->get('file');
        if ($legacy instanceof UploadedFile) {
            return $legacy;
        }

        return null;
    }

    private function normalizeAudioDurationSeconds(Request $request): ?int
    {
        $durationSeconds = $request->request->get('duration');
        if ($durationSeconds !== null && $durationSeconds !== '') {
            $normalized = filter_var($durationSeconds, FILTER_VALIDATE_INT);
            return $normalized === false ? null : max(0, (int) $normalized);
        }

        $durationMs = $request->request->get('durationMs');
        if ($durationMs === null || $durationMs === '') {
            return null;
        }

        $normalizedMs = filter_var($durationMs, FILTER_VALIDATE_INT);
        if ($normalizedMs === false) {
            return null;
        }

        return (int) ceil(max(0, (int) $normalizedMs) / 1000);
    }

    private function isAllowedAudioMimeType(string $mimeType): bool
    {
        if ($mimeType === '') {
            return false;
        }

        if (in_array($mimeType, $this->chatAudioAllowedMimeTypes, true)) {
            return true;
        }

        return str_starts_with($mimeType, 'audio/');
    }

    private function isAudioAttachment(MessageAttachment $attachment): bool
    {
        if ($attachment->getType() === 'audio') {
            return true;
        }

        return str_starts_with(strtolower($attachment->getMimeType()), 'audio/');
    }

    private function getAttachmentSummaryErrorMessage(\Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        if ($message === '') {
            return 'Impossible de generer le resume du fichier.';
        }

        return mb_substr($message, 0, 240);
    }

    private function isCombinedAiSummary(string $summaryText): bool
    {
        $normalized = mb_strtolower($summaryText);
        return str_contains($normalized, 'traduction des messages de conversation');
    }

    private function isLegacyDetailedPdfSummary(string $summaryText): bool
    {
        $normalized = mb_strtolower($summaryText);

        return str_contains($normalized, '2) points cles')
            || str_contains($normalized, '3) actions / infos importantes')
            || str_contains($normalized, 'analyse du document pdf');
    }

    private function assertCsrf(Request $request): void
    {
        $token = $request->headers->get('X-CSRF-TOKEN') ?? $request->request->get('_token');
        if (!$this->isCsrfTokenValid('chat_action', (string) $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
