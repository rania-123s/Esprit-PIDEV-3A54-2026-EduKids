<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ConversationParticipantRepository;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ConversationVoter;
use App\Service\ChatWebSocketTokenService;
use App\Service\ChatService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chat')]
class ConversationController extends AbstractController
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly ConversationParticipantRepository $participantRepository,
        private readonly MessageRepository $messageRepository,
        private readonly UserRepository $userRepository,
        private readonly ChatService $chatService,
        private readonly ChatWebSocketTokenService $chatWebSocketTokenService,
        #[Autowire('%env(CHAT_WS_PUBLIC_URL)%')]
        private readonly string $chatWebSocketUrl
    ) {
    }

    #[Route('', name: 'chat_entry', methods: ['GET'])]
    #[Route('', name: 'chat_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        return $this->renderChatPage();
    }

    #[Route('/{id}', name: 'chat_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function show(Conversation $conversation): Response
    {
        $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);

        return $this->renderChatPage($conversation);
    }

    #[Route('/conversations', name: 'chat_conversations', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listConversations(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $search = $request->query->get('q');
        $conversations = $this->conversationRepository->findForUser($user, $search);

        $data = [];
        foreach ($conversations as $conversation) {
            $data[] = $this->serializeConversationListItem($conversation, $user);
        }

        return $this->json($data);
    }

    #[Route('/conversations/{id}/summary', name: 'chat_conversation_summary', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function conversationSummary(Conversation $conversation): JsonResponse
    {
        $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->serializeConversationListItem($conversation, $user));
    }

    #[Route('/private', name: 'chat_private_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createPrivate(Request $request): JsonResponse
    {
        $this->assertCsrf($request);
        /** @var User $actor */
        $actor = $this->getUser();
        $this->assertCanUseChat($actor);

        $payload = $this->getPayload($request);
        $parentId = (int) ($payload['parentId'] ?? 0);
        if ($parentId <= 0) {
            return $this->json(['error' => 'parentId is required.'], Response::HTTP_BAD_REQUEST);
        }

        $otherParent = $this->userRepository->find($parentId);
        if (!$otherParent instanceof User || !$this->isParentRole($otherParent)) {
            return $this->json(['error' => 'Parent not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($otherParent->getId() === $actor->getId()) {
            return $this->json(['error' => 'Cannot create a private conversation with yourself.'], Response::HTTP_BAD_REQUEST);
        }

        $conversation = $this->chatService->createOrGetPrivateConversation($actor, $otherParent);

        return $this->json([
            'id' => $conversation->getId(),
            'title' => $this->getConversationTitle($conversation, $actor),
            'redirectUrl' => $this->generateUrl('chat_show', ['id' => $conversation->getId()]),
        ]);
    }

    #[Route('/group', name: 'chat_group_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createGroup(Request $request): JsonResponse
    {
        $this->assertCsrf($request);
        /** @var User $actor */
        $actor = $this->getUser();
        $this->assertCanUseChat($actor);

        $payload = $this->getPayload($request);
        $title = trim((string) ($payload['title'] ?? $payload['groupName'] ?? ''));
        if ($title === '') {
            return $this->json(['error' => 'Group name is required.'], Response::HTTP_BAD_REQUEST);
        }
        if (mb_strlen($title) > 120) {
            return $this->json(['error' => 'Group name is too long (max 120).'], Response::HTTP_BAD_REQUEST);
        }

        $memberIds = $payload['memberIds'] ?? [];
        if (!is_array($memberIds)) {
            return $this->json(['error' => 'memberIds must be an array.'], Response::HTTP_BAD_REQUEST);
        }

        $memberIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): int => (int) $value, $memberIds),
            static fn (int $id): bool => $id > 0
        )));

        $memberIds = array_values(array_filter(
            $memberIds,
            static fn (int $id): bool => $id !== $actor->getId()
        ));

        if (count($memberIds) < 2) {
            return $this->json(['error' => 'Select at least 2 other parents.'], Response::HTTP_BAD_REQUEST);
        }

        $selectedParents = [];
        foreach ($memberIds as $memberId) {
            $parent = $this->userRepository->find($memberId);
            if (!$parent instanceof User || !$this->isParentRole($parent)) {
                return $this->json(['error' => sprintf('Parent %d not found.', $memberId)], Response::HTTP_NOT_FOUND);
            }
            $selectedParents[] = $parent;
        }

        try {
            $conversation = $this->chatService->createGroupConversation($actor, $selectedParents, $title);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'id' => $conversation->getId(),
            'title' => $this->getConversationTitle($conversation, $actor),
            'redirectUrl' => $this->generateUrl('chat_show', ['id' => $conversation->getId()]),
        ]);
    }

    #[Route('/{id}/leave', name: 'chat_group_leave', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function leaveGroup(Conversation $conversation, Request $request): JsonResponse
    {
        $this->assertCsrf($request);
        $this->denyAccessUnlessGranted(ConversationVoter::LEAVE, $conversation);
        /** @var User $actor */
        $actor = $this->getUser();

        $this->chatService->leaveGroup($conversation, $actor);
        $this->chatService->publishTypingEvent($conversation, $actor, false);

        return $this->json([
            'ok' => true,
            'redirectUrl' => $this->generateUrl('chat_index'),
        ]);
    }

    #[Route('/{id}/members/add', name: 'chat_group_members_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function addMember(Conversation $conversation, Request $request): JsonResponse
    {
        $this->assertCsrf($request);
        $this->denyAccessUnlessGranted(ConversationVoter::MANAGE_MEMBERS, $conversation);

        $payload = $this->getPayload($request);
        $memberId = (int) ($payload['memberId'] ?? 0);
        if ($memberId <= 0) {
            return $this->json(['error' => 'memberId is required.'], Response::HTTP_BAD_REQUEST);
        }

        $member = $this->userRepository->find($memberId);
        if (!$member instanceof User || !$this->isParentRole($member)) {
            return $this->json(['error' => 'Parent not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->chatService->addMemberToGroup($conversation, $member);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/{id}/members', name: 'chat_group_members_list', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function listMembers(Conversation $conversation): JsonResponse
    {
        $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);
        /** @var User $viewer */
        $viewer = $this->getUser();

        $members = $this->participantRepository->findActiveMembers($conversation);
        $data = array_map(function ($participant) use ($viewer): array {
            $member = $participant->getUser();

            return [
                'id' => $member?->getId(),
                'name' => $this->getDisplayName($member),
                'email' => $member?->getEmail(),
                'role' => $participant->getRole(),
                'isCurrentUser' => $member?->getId() === $viewer->getId(),
            ];
        }, $members);

        return $this->json([
            'isGroup' => $conversation->isGroup(),
            'canManage' => $this->participantRepository->isGroupAdmin($conversation, $viewer),
            'items' => $data,
        ]);
    }

    #[Route('/{id}/hide', name: 'chat_conversation_hide', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function hideConversation(Conversation $conversation, Request $request): JsonResponse
    {
        $this->assertCsrf($request);
        $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);
        /** @var User $actor */
        $actor = $this->getUser();

        $this->chatService->hideConversationForUser($conversation, $actor);
        $this->chatService->publishTypingEvent($conversation, $actor, false);

        return $this->json([
            'ok' => true,
            'conversationId' => $conversation->getId(),
        ]);
    }

    #[Route('/{id}/typing', name: 'chat_conversation_typing', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function typing(Conversation $conversation, Request $request): JsonResponse
    {
        $this->assertCsrf($request);
        $this->denyAccessUnlessGranted(ConversationVoter::MESSAGE, $conversation);
        /** @var User $actor */
        $actor = $this->getUser();

        $payload = $this->getPayload($request);
        $typing = filter_var($payload['typing'] ?? null, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if (!is_bool($typing)) {
            return $this->json(['error' => 'typing boolean is required.'], Response::HTTP_BAD_REQUEST);
        }

        $this->chatService->publishTypingEvent($conversation, $actor, $typing);

        return $this->json(['ok' => true]);
    }

    #[Route('/{id}/members/remove', name: 'chat_group_members_remove', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function removeMember(Conversation $conversation, Request $request): JsonResponse
    {
        $this->assertCsrf($request);
        $this->denyAccessUnlessGranted(ConversationVoter::MANAGE_MEMBERS, $conversation);
        /** @var User $actor */
        $actor = $this->getUser();

        $payload = $this->getPayload($request);
        $memberId = (int) ($payload['memberId'] ?? 0);
        if ($memberId <= 0) {
            return $this->json(['error' => 'memberId is required.'], Response::HTTP_BAD_REQUEST);
        }

        if ($memberId === $actor->getId()) {
            return $this->json(['error' => 'Use leave endpoint to quit the group.'], Response::HTTP_BAD_REQUEST);
        }

        $member = $this->userRepository->find($memberId);
        if (!$member instanceof User) {
            return $this->json(['error' => 'Member not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->chatService->removeMemberFromGroup($conversation, $member);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    private function renderChatPage(?Conversation $conversation = null): Response
    {
        /** @var User $viewer */
        $viewer = $this->getUser();
        $template = $this->isGranted('ROLE_ADMIN')
            ? 'chat/admin_chat.html.twig'
            : 'chat/chat_parent.html.twig';

        return $this->render($template, [
            'initial_conversation_id' => $conversation?->getId(),
            'initial_conversation_title' => $conversation ? $this->getConversationTitle($conversation, $viewer) : null,
            'websocket_url' => $this->chatWebSocketUrl,
            'websocket_token' => $viewer->getId() !== null
                ? $this->chatWebSocketTokenService->createToken($viewer->getId())
                : '',
        ]);
    }

    private function getPayload(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (JsonException) {
            return $request->request->all();
        }
    }

    private function getConversationTitle(Conversation $conversation, User $viewer): string
    {
        if ($conversation->isGroup()) {
            $title = trim((string) $conversation->getTitle());

            return $title !== '' ? $title : 'Groupe';
        }

        return $this->getPrivateConversationTitle($conversation, $viewer);
    }

    private function getPrivateConversationTitle(Conversation $conversation, User $viewer): string
    {
        // Private conversation title must always be the other participant full name.
        $activeMembers = $this->participantRepository->findActiveMembers($conversation);
        foreach ($activeMembers as $member) {
            $user = $member->getUser();
            if ($user === null || $user->getId() === $viewer->getId()) {
                continue;
            }

            return $this->getPrivateParticipantName($user);
        }

        // Fallback for inconsistent legacy data.
        foreach ($conversation->getParticipants() as $participant) {
            $user = $participant->getUser();
            if ($user !== null && $user->getId() !== $viewer->getId()) {
                return $this->getPrivateParticipantName($user);
            }
        }

        return 'Conversation privee';
    }

    private function getPrivateParticipantName(User $user): string
    {
        $lastName = trim((string) $user->getLastName());
        $firstName = trim((string) $user->getFirstName());

        $parts = array_values(array_filter([$lastName, $firstName]));
        if ($parts !== []) {
            return implode(' ', $parts);
        }

        return $this->getDisplayName($user);
    }

    private function formatLastMessagePreview(?Message $lastMessage, User $viewer): string
    {
        if (!$lastMessage instanceof Message) {
            return '';
        }

        if ($lastMessage->getDeletedAt() !== null) {
            return 'Message supprime';
        }

        $content = preg_replace('/\s+/', ' ', trim((string) $lastMessage->getContent()));
        if (is_string($content) && $content !== '') {
            return $this->truncatePreview($content, 55);
        }

        $hasAudioAttachment = $lastMessage->getType() === 'audio';
        if (!$hasAudioAttachment) {
            foreach ($lastMessage->getAttachments() as $attachment) {
                if ($attachment->getType() === 'audio' || str_starts_with($attachment->getMimeType(), 'audio/')) {
                    $hasAudioAttachment = true;
                    break;
                }
            }
        }

        if ($hasAudioAttachment) {
            $sender = $lastMessage->getSender();
            if ($sender !== null && $sender->getId() === $viewer->getId()) {
                return 'Message vocal';
            }

            return sprintf('%s a envoye un message vocal', $this->getDisplayName($sender));
        }

        $hasAttachment = $lastMessage->getType() === 'image'
            || $lastMessage->getType() === 'file'
            || ($lastMessage->getFilePath() !== null && $lastMessage->getFilePath() !== '');

        if ($hasAttachment) {
            $sender = $lastMessage->getSender();
            if ($sender !== null && $sender->getId() === $viewer->getId()) {
                return 'Vous avez envoyé une pièce jointe';
            }

            return sprintf('%s a envoyé une pièce jointe', $this->getDisplayName($sender));
        }

        return 'Message';
    }

    private function truncatePreview(string $value, int $maxLength): string
    {
        if ($maxLength < 4 || mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $maxLength - 3)) . '...';
    }

    private function serializeConversationListItem(Conversation $conversation, User $user): array
    {
        $lastMessage = $this->messageRepository->findLastMessage($conversation);

        return [
            'id' => $conversation->getId(),
            'title' => $this->getConversationTitle($conversation, $user),
            'isGroup' => $conversation->isGroup(),
            'isAdmin' => $conversation->isGroup()
                ? $this->participantRepository->isGroupAdmin($conversation, $user)
                : false,
            'lastMessage' => $this->formatLastMessagePreview($lastMessage, $user),
            'lastMessageAt' => $lastMessage?->getCreatedAt()?->format(DATE_ATOM),
        ];
    }

    private function getDisplayName(?User $user): string
    {
        if ($user === null) {
            return 'Utilisateur';
        }

        $parts = array_filter([$user->getFirstName(), $user->getLastName()]);
        if ($parts !== []) {
            return implode(' ', $parts);
        }

        return (string) $user->getEmail();
    }

    private function assertCsrf(Request $request): void
    {
        $token = $request->headers->get('X-CSRF-TOKEN') ?? $request->request->get('_token');
        if (!$this->isCsrfTokenValid('chat_action', (string) $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function isParentRole(User $user): bool
    {
        return in_array('ROLE_PARENT', $user->getRoles(), true);
    }

    private function canUseChat(User $user): bool
    {
        $roles = $user->getRoles();

        return in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_PARENT', $roles, true);
    }

    private function assertCanUseChat(User $user): void
    {
        if ($this->canUseChat($user)) {
            return;
        }

        throw $this->createAccessDeniedException('Only admins and parents can perform this action.');
    }
}
