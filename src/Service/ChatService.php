<?php

namespace App\Service;

use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Message;
use App\Entity\MessageAttachment;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\ConversationParticipantRepository;
use App\Repository\ConversationRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ChatService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConversationRepository $conversationRepository,
        private readonly ConversationParticipantRepository $participantRepository,
        private readonly UserRepository $userRepository,
        private readonly HubInterface $hub,
        private readonly HttpClientInterface $httpClient,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%chat_upload_dir%')]
        private readonly string $chatUploadDir,
        #[Autowire('%chat_upload_public_prefix%')]
        private readonly string $chatUploadPublicPrefix,
        #[Autowire('%chat_upload_max_size_bytes%')]
        private readonly int $chatUploadMaxSizeBytes,
        #[Autowire('%chat_upload_max_files_per_message%')]
        private readonly int $chatUploadMaxFilesPerMessage,
        #[Autowire('%chat_upload_allowed_mime_types%')]
        private readonly array $chatUploadAllowedMimeTypes,
        #[Autowire('%chat_auto_reply_admin_email%')]
        private readonly string $chatAutoReplyAdminEmail,
        #[Autowire('%chat_auto_reply_cooldown_minutes%')]
        private readonly int $chatAutoReplyCooldownMinutes,
        #[Autowire('%env(CHAT_WS_BRIDGE_URL)%')]
        private readonly string $chatWebSocketBridgeUrl,
        #[Autowire('%env(CHAT_WS_BRIDGE_SECRET)%')]
        private readonly string $chatWebSocketBridgeSecret,
        #[Autowire('%kernel.secret%')]
        private readonly string $kernelSecret,
        private readonly LoggerInterface $logger
    ) {
    }

    public function createOrGetPrivateConversation(User $userA, User $userB): Conversation
    {
        $userAId = $userA->getId();
        $userBId = $userB->getId();

        if ($userAId === null || $userBId === null || $userAId === $userBId) {
            throw new \InvalidArgumentException('Invalid private conversation members.');
        }

        $privateKey = $this->conversationRepository->buildPrivateKey($userAId, $userBId);
        $existing = $this->conversationRepository->findPrivateBetweenUsers($userA, $userB);

        if ($existing instanceof Conversation) {
            $existing
                ->setIsGroup(false)
                ->setTitle(null)
                ->setPrivateKey($privateKey)
                ->setUpdatedAt(new \DateTimeImmutable());

            $this->ensureParticipant($existing, $userA, ConversationParticipant::ROLE_MEMBER);
            $this->ensureParticipant($existing, $userB, ConversationParticipant::ROLE_MEMBER);
            $this->em->flush();

            return $existing;
        }

        $conversation = (new Conversation())
            ->setIsGroup(false)
            ->setTitle(null)
            ->setPrivateKey($privateKey);

        $this->em->persist($conversation);
        $this->attachParticipant($conversation, $userA, ConversationParticipant::ROLE_MEMBER);
        $this->attachParticipant($conversation, $userB, ConversationParticipant::ROLE_MEMBER);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            $alreadyCreated = $this->conversationRepository->findPrivateBetweenUsers($userA, $userB);
            if ($alreadyCreated instanceof Conversation) {
                return $alreadyCreated;
            }

            throw new \RuntimeException('Unable to create private conversation.');
        }

        $this->publishConversationUpdate($conversation);

        return $conversation;
    }

    /**
     * @param User[] $selectedParents
     */
    public function createGroupConversation(User $creator, array $selectedParents, string $groupName): Conversation
    {
        $groupName = trim($groupName);
        if ($groupName === '') {
            throw new \InvalidArgumentException('Group name is required.');
        }

        $members = $this->normalizeGroupMembers($creator, $selectedParents);
        if (count($members) < 3) {
            throw new \InvalidArgumentException('A group must include at least 3 members.');
        }

        $conversation = (new Conversation())
            ->setIsGroup(true)
            ->setTitle($groupName)
            ->setPrivateKey(null);

        $this->em->persist($conversation);

        foreach ($members as $member) {
            $role = $member->getId() === $creator->getId()
                ? ConversationParticipant::ROLE_ADMIN
                : ConversationParticipant::ROLE_MEMBER;
            $this->attachParticipant($conversation, $member, $role);
        }

        $this->em->flush();
        $this->publishConversationUpdate($conversation);

        return $conversation;
    }

    public function leaveGroup(Conversation $conversation, User $actor): void
    {
        if (!$conversation->isGroup()) {
            throw new \InvalidArgumentException('Only group conversations can be left.');
        }

        $membership = $this->participantRepository->findActiveForConversationAndUser($conversation, $actor);
        if (!$membership instanceof ConversationParticipant) {
            throw new \RuntimeException('You are not a member of this conversation.');
        }

        $wasAdmin = $membership->isAdmin();
        $membership->setDeletedAt(new \DateTimeImmutable());
        $conversation->setUpdatedAt(new \DateTimeImmutable());

        if ($wasAdmin) {
            $this->ensureAtLeastOneGroupAdmin($conversation);
        }

        $this->em->flush();
        $this->publishConversationUpdate($conversation);
    }

    public function hideConversationForUser(Conversation $conversation, User $actor): void
    {
        $membership = $this->participantRepository->findForConversationAndUser($conversation, $actor);
        if (!$membership instanceof ConversationParticipant || $membership->getDeletedAt() !== null) {
            throw new \RuntimeException('You are not a member of this conversation.');
        }

        if ($membership->getHiddenAt() !== null) {
            return;
        }

        $membership->setHiddenAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    public function addMemberToGroup(Conversation $conversation, User $member): void
    {
        if (!$conversation->isGroup()) {
            throw new \InvalidArgumentException('Members can be added only to groups.');
        }

        $existing = $this->participantRepository->findForConversationAndUser($conversation, $member);
        if ($existing instanceof ConversationParticipant) {
            if ($existing->getDeletedAt() === null) {
                throw new \InvalidArgumentException('User is already a member of this group.');
            }

            $existing
                ->setDeletedAt(null)
                ->setHiddenAt(null)
                ->setRole(ConversationParticipant::ROLE_MEMBER);
        } else {
            $this->attachParticipant($conversation, $member, ConversationParticipant::ROLE_MEMBER);
        }

        $conversation->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
        $this->publishConversationUpdate($conversation);
    }

    public function removeMemberFromGroup(Conversation $conversation, User $member): void
    {
        if (!$conversation->isGroup()) {
            throw new \InvalidArgumentException('Members can be removed only from groups.');
        }

        $membership = $this->participantRepository->findActiveForConversationAndUser($conversation, $member);
        if (!$membership instanceof ConversationParticipant) {
            throw new \InvalidArgumentException('User is not an active group member.');
        }

        $wasAdmin = $membership->isAdmin();
        $membership->setDeletedAt(new \DateTimeImmutable());
        $conversation->setUpdatedAt(new \DateTimeImmutable());

        if ($wasAdmin) {
            $this->ensureAtLeastOneGroupAdmin($conversation);
        }

        $this->em->flush();
        $this->publishConversationUpdate($conversation);
    }

    public function createMessage(
        Conversation $conversation,
        User $sender,
        string $content,
        array $files = []
    ): Message {
        $content = trim($content);
        $normalizedFiles = $this->normalizeUploadedFiles($files);
        if ($content === '' && $normalizedFiles === []) {
            throw new \InvalidArgumentException('Empty message.');
        }

        if (count($normalizedFiles) > $this->chatUploadMaxFilesPerMessage) {
            throw new \InvalidArgumentException(sprintf(
                'Too many attachments. Maximum allowed: %d.',
                $this->chatUploadMaxFilesPerMessage
            ));
        }

        $message = new Message();
        $message
            ->setConversation($conversation)
            ->setSender($sender)
            ->setContent($content)
            ->setStatus('sent')
            ->setType($normalizedFiles === [] ? 'text' : 'file')
            ->setFilePath(null);

        foreach ($normalizedFiles as $file) {
            $storedAttachment = $this->storeUploadedFile($file);
            $attachment = (new MessageAttachment())
                ->setOriginalName($storedAttachment['originalName'])
                ->setStoredName($storedAttachment['storedName'])
                ->setStoragePath($storedAttachment['storagePath'])
                ->setMimeType($storedAttachment['mimeType'])
                ->setSize($storedAttachment['size'])
                ->setIsImage($storedAttachment['isImage'])
                ->setType($storedAttachment['type'])
                ->setDuration($storedAttachment['duration']);

            $message->addAttachment($attachment);
        }

        if ($normalizedFiles !== [] && $content === '' && $this->messageHasOnlyImages($message)) {
            $message->setType('image');
        }

        $this->restoreHiddenParticipantsOnNewMessage($conversation, $sender);
        $conversation->setUpdatedAt(new \DateTimeImmutable());
        $this->em->persist($message);
        $this->createMessageNotifications($conversation, $sender, $message);
        $this->em->flush();

        $this->publishTypingEvent($conversation, $sender, false);
        $this->publishMessageEvent('message.created', $conversation, $message);
        $this->publishConversationUpdate($conversation);
        $this->maybeSendAutoReply($conversation, $sender, $content);

        return $message;
    }

    public function createAudioMessage(
        Conversation $conversation,
        User $sender,
        UploadedFile $file,
        ?int $duration = null
    ): Message {
        $storedAttachment = $this->storeUploadedFile($file, 'audio');
        $attachment = (new MessageAttachment())
            ->setOriginalName($storedAttachment['originalName'])
            ->setStoredName($storedAttachment['storedName'])
            ->setStoragePath($storedAttachment['storagePath'])
            ->setMimeType($storedAttachment['mimeType'])
            ->setSize($storedAttachment['size'])
            ->setIsImage(false)
            ->setType('audio')
            ->setDuration($duration);

        $message = (new Message())
            ->setConversation($conversation)
            ->setSender($sender)
            ->setContent('')
            ->setStatus('sent')
            ->setType('audio')
            ->setFilePath(null)
            ->addAttachment($attachment);

        $this->restoreHiddenParticipantsOnNewMessage($conversation, $sender);
        $conversation->setUpdatedAt(new \DateTimeImmutable());
        $this->em->persist($message);
        $this->createMessageNotifications($conversation, $sender, $message);
        $this->em->flush();

        $this->publishTypingEvent($conversation, $sender, false);
        $this->publishMessageEvent('message.created', $conversation, $message);
        $this->publishConversationUpdate($conversation);

        return $message;
    }

    public function updateMessage(Message $message, User $editor, string $content): void
    {
        if ($message->getSender()?->getId() !== $editor->getId()) {
            throw new \RuntimeException('Not allowed.');
        }

        $message->setContent($content);
        $message->setUpdatedAt(new \DateTimeImmutable());
        $message->getConversation()?->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        $conversation = $message->getConversation();
        if ($conversation instanceof Conversation) {
            $this->publishMessageEvent('message.updated', $conversation, $message);
            $this->publishConversationUpdate($conversation);
        }
    }

    public function deleteMessage(Message $message, User $actor): void
    {
        if ($message->getSender()?->getId() !== $actor->getId()) {
            throw new \RuntimeException('Not allowed.');
        }

        $message->setDeletedAt(new \DateTimeImmutable());
        $message->getConversation()?->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        $conversation = $message->getConversation();
        if ($conversation instanceof Conversation) {
            $this->publishMessageEvent('message.deleted', $conversation, $message);
            $this->publishConversationUpdate($conversation);
        }
    }

    public function publishTypingEvent(Conversation $conversation, User $actor, bool $typing): void
    {
        $actorId = $actor->getId();
        if ($actorId === null) {
            return;
        }

        $event = [
            'type' => 'conversation.typing',
            'conversationId' => $conversation->getId(),
            'typing' => $typing,
            'userId' => $actorId,
            'userName' => $this->buildDisplayName($actor),
        ];

        $payload = json_encode($event, JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            return;
        }

        $recipientUserIds = [];
        foreach ($conversation->getParticipants() as $participant) {
            if ($participant->getDeletedAt() !== null || $participant->getHiddenAt() !== null) {
                continue;
            }

            $user = $participant->getUser();
            if ($user === null) {
                continue;
            }

            $recipientUserIds[] = (int) $user->getId();
            $this->safePublish($this->getUserTopic($user), $payload);
        }

        $this->safePublishWebSocketBridge($event, $recipientUserIds);
    }

    private function maybeSendAutoReply(Conversation $conversation, User $sender, string $content): void
    {
        if (trim($content) === '') {
            return;
        }

        $admin = $this->resolveAutoReplyAdmin();
        if (!$admin instanceof User) {
            return;
        }

        $senderId = $sender->getId();
        $adminId = $admin->getId();
        if ($senderId === null || $adminId === null) {
            return;
        }

        // Auto-reply is enabled only for admin senders.
        if (!in_array('ROLE_ADMIN', $sender->getRoles(), true)) {
            return;
        }

        // Never auto-reply to messages sent by the auto-reply account to avoid loops.
        if ($senderId === $adminId) {
            return;
        }

        $replyTemplate = $this->resolveAutoReplyTemplate($content);
        if ($replyTemplate === null) {
            return;
        }

        $cooldownMinutes = max(0, $this->chatAutoReplyCooldownMinutes);
        $now = new \DateTimeImmutable();
        $lastAutoReplyAt = $conversation->getLastAutoReplyAt();
        if ($cooldownMinutes > 0 && $lastAutoReplyAt instanceof \DateTimeImmutable) {
            $nextAllowedAt = $lastAutoReplyAt->modify(sprintf('+%d minutes', $cooldownMinutes));
            if ($nextAllowedAt instanceof \DateTimeImmutable && $now < $nextAllowedAt) {
                return;
            }
        }

        $message = (new Message())
            ->setConversation($conversation)
            ->setSender($admin)
            ->setContent($this->personalizeAutoReplyTemplate($replyTemplate, $sender))
            ->setStatus('sent')
            ->setType('text')
            ->setFilePath(null);

        $conversation
            ->setLastAutoReplyAt($now)
            ->setUpdatedAt($now);

        $this->em->persist($message);
        $this->createMessageNotifications($conversation, $admin, $message);
        $this->em->flush();

        $this->publishMessageEvent('message.created', $conversation, $message);
        $this->publishConversationUpdate($conversation);
    }

    private function resolveAutoReplyAdmin(): ?User
    {
        $admin = $this->userRepository->findOneAdmin();
        if ($admin instanceof User) {
            return $admin;
        }

        $email = trim($this->chatAutoReplyAdminEmail);
        if ($email === '') {
            return null;
        }

        $fallbackAdmin = $this->userRepository->findOneBy(['email' => $email]);
        return $fallbackAdmin instanceof User ? $fallbackAdmin : null;
    }

    private function resolveAutoReplyTemplate(string $content): ?string
    {
        $normalizedContent = $this->normalizeAutoReplyText($content);
        if ($normalizedContent === '') {
            return null;
        }

        foreach ($this->getAutoReplyRules() as $rule) {
            foreach ($rule['triggers'] as $trigger) {
                if (!$this->autoReplyTriggerMatches($normalizedContent, $trigger)) {
                    continue;
                }

                return $this->pickRandomResponse($rule['responses']);
            }
        }

        return null;
    }

    private function autoReplyTriggerMatches(string $normalizedContent, string $trigger): bool
    {
        $normalizedTrigger = trim($trigger);
        if ($normalizedContent === '' || $normalizedTrigger === '') {
            return false;
        }

        if ($normalizedContent === $normalizedTrigger) {
            return true;
        }

        $pattern = '/(^|\s)' . preg_quote($normalizedTrigger, '/') . '(\s|$)/';
        return preg_match($pattern, $normalizedContent) === 1;
    }

    /**
     * @return array<int, array{triggers: array<int, string>, responses: array<int, string>}>
     */
    private function getAutoReplyRules(): array
    {
        $rules = [
            // 8) Version professionnelle
            [
                'triggers' => ['bonjour'],
                'responses' => [
                    'Bonjour {Nom}, merci de nous avoir contactés. Comment puis-je vous assister aujourd’hui ?',
                ],
            ],
            // 9) Version moderne / conviviale
            [
                'triggers' => ['bjr'],
                'responses' => [
                    'Bonjour {Nom} 😄 Que puis-je faire pour vous ?',
                ],
            ],
            // 10) Version courte automatique
            [
                'triggers' => ['salut'],
                'responses' => [
                    'Bonjour {Nom} 👋',
                ],
            ],
            // 1) Salutations simples
            [
                'triggers' => ['bjr', 'slt', 'coucou', 'hey', 'cc', 'yo', 're bonjour', 'bonjour à tous', 'bonjour admin', 'salut admin'],
                'responses' => [
                    'Bonjour {Nom} 👋',
                    'Bonjour {Nom}, comment puis-je vous aider ?',
                    'Salut {Nom} 😄',
                    'Bonjour {Nom}, bienvenue !',
                    'Bonjour {Nom}, je suis disponible pour vous aider.',
                ],
            ],
            // 2) Message de politesse
            [
                'triggers' => ['comment ça va ?', 'vous allez bien ?', 'ça va admin ?', 'ça va ?', 'tout va bien ?'],
                'responses' => [
                    'Merci {Nom}, je vais très bien 😊',
                    'Je vais bien {Nom}, comment puis-je vous aider ?',
                    'Merci pour votre message {Nom}.',
                ],
            ],
            // 3) Demande d’aide
            [
                'triggers' => ['aide', 'help', 'besoin d’aide', 'pouvez-vous m’aider ?', 'j’ai un problème', 'problème'],
                'responses' => [
                    'Bien sûr {Nom}, expliquez-moi votre problème.',
                    'Je suis là pour vous aider {Nom}.',
                    'Pouvez-vous me donner plus de détails ?',
                ],
            ],
            // 4) Remerciements
            [
                'triggers' => ['merci', 'merci beaucoup', 'thanks', 'merci admin', 'c’est bon merci'],
                'responses' => [
                    'Avec plaisir {Nom} 😊',
                    'Je vous en prie {Nom}.',
                    'Toujours à votre service 👌',
                ],
            ],
            // 5) Messages courts familiers
            [
                'triggers' => ['ok', 'd’accord', 'c bon', 'parfait', 'nickel'],
                'responses' => [
                    'Très bien {Nom} 👍',
                    'Parfait {Nom}.',
                    'D’accord, n’hésitez pas si besoin.',
                ],
            ],
            // 6) Messages du soir / matin
            [
                'triggers' => ['bon matin', 'bon après-midi', 'bonne soirée', 'bonne nuit'],
                'responses' => [
                    'Bon après-midi {Nom} ☀️',
                    'Bonne soirée {Nom} 🌙',
                    'Bonne nuit {Nom}, à demain !',
                ],
            ],
            // 7) Messages en groupe
            [
                'triggers' => ['bonjour tout le monde', 'salut à tous', 'hello groupe'],
                'responses' => [
                    'Bonjour {Nom} et bienvenue à tous 👋',
                    'Salut {Nom} 😊',
                ],
            ],
        ];

        foreach ($rules as &$rule) {
            $rule['triggers'] = array_values(array_filter(
                array_map(fn (string $trigger): string => $this->normalizeAutoReplyText($trigger), $rule['triggers']),
                static fn (string $trigger): bool => $trigger !== ''
            ));
        }
        unset($rule);

        return $rules;
    }

    /**
     * @param string[] $responses
     */
    private function pickRandomResponse(array $responses): ?string
    {
        $available = array_values(array_filter(
            array_map(static fn (string $response): string => trim($response), $responses),
            static fn (string $response): bool => $response !== ''
        ));

        if ($available === []) {
            return null;
        }

        if (count($available) === 1) {
            return $available[0];
        }

        return $available[random_int(0, count($available) - 1)];
    }

    private function personalizeAutoReplyTemplate(string $template, User $recipient): string
    {
        $firstName = trim((string) ($recipient->getFirstName() ?? ''));
        $lastName = trim((string) ($recipient->getLastName() ?? ''));
        $email = trim((string) ($recipient->getEmail() ?? ''));
        $emailLocalPart = $email !== '' ? explode('@', $email)[0] : '';
        $fallbackName = $firstName !== ''
            ? $firstName
            : ($lastName !== '' ? $lastName : ($emailLocalPart !== '' ? $emailLocalPart : 'Utilisateur'));

        $nameForNom = $lastName !== '' ? $lastName : $fallbackName;
        $nameForPrenom = $firstName !== '' ? $firstName : $fallbackName;

        return strtr($template, [
            '{Nom}' => $nameForNom,
            '{Prénom}' => $nameForPrenom,
            '{Prenom}' => $nameForPrenom,
        ]);
    }

    private function normalizeAutoReplyText(string $text): string
    {
        $normalized = mb_strtolower(trim($text));
        if ($normalized === '') {
            return '';
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($transliterated) && trim($transliterated) !== '') {
            $normalized = strtolower($transliterated);
        }

        $normalized = str_replace(['’', '\''], ' ', $normalized);
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    public function serializeMessage(Message $message): array
    {
        $isDeleted = $message->getDeletedAt() !== null;
        $attachments = $isDeleted ? [] : $this->serializeAttachments($message);
        $serializedType = $isDeleted ? 'text' : $this->resolveSerializedMessageType($message, $attachments);
        $fallbackFilePath = $attachments[0]['url'] ?? ($isDeleted ? null : $message->getFilePath());

        return [
            'id' => $message->getId(),
            'content' => $isDeleted ? 'Message supprime' : $message->getContent(),
            'type' => $serializedType,
            'status' => $message->getStatus(),
            'filePath' => $fallbackFilePath,
            'attachments' => $attachments,
            'senderId' => $message->getSender()?->getId(),
            'senderName' => $this->buildDisplayName($message->getSender()),
            'createdAt' => $message->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $message->getUpdatedAt()?->format(DATE_ATOM),
            'deletedAt' => $message->getDeletedAt()?->format(DATE_ATOM),
        ];
    }

    public function getConversationTopic(Conversation $conversation): string
    {
        return 'chat/conversation/' . $conversation->getId();
    }

    public function getUserTopic(User $user): string
    {
        return 'chat/user/' . $user->getId();
    }

    private function ensureAtLeastOneGroupAdmin(Conversation $conversation): void
    {
        $members = $this->participantRepository->findActiveMembers($conversation);
        $hasAdmin = false;

        foreach ($members as $member) {
            if ($member->isAdmin()) {
                $hasAdmin = true;
                break;
            }
        }

        if ($hasAdmin || $members === []) {
            return;
        }

        $members[0]->setRole(ConversationParticipant::ROLE_ADMIN);
    }

    private function restoreHiddenParticipantsOnNewMessage(Conversation $conversation, User $sender): void
    {
        $senderId = $sender->getId();
        foreach ($this->participantRepository->findHiddenActiveMembers($conversation) as $participant) {
            $participantUser = $participant->getUser();
            if ($participantUser === null) {
                continue;
            }

            if ($senderId !== null && $participantUser->getId() === $senderId) {
                continue;
            }

            $participant->setHiddenAt(null);
        }
    }

    private function createMessageNotifications(Conversation $conversation, User $sender, Message $message): void
    {
        $conversationId = $conversation->getId();
        $senderId = $sender->getId();
        if ($conversationId === null || $senderId === null) {
            return;
        }

        $notificationText = $this->buildMessageNotificationText($sender, $message);
        foreach ($conversation->getParticipants() as $participant) {
            if ($participant->getDeletedAt() !== null || $participant->getHiddenAt() !== null) {
                continue;
            }

            $receiver = $participant->getUser();
            if ($receiver === null || $receiver->getId() === $senderId) {
                continue;
            }

            $notification = (new Notification())
                ->setReceiver($receiver)
                ->setSender($sender)
                ->setType(Notification::TYPE_MESSAGE)
                ->setConversationId($conversationId)
                ->setText($notificationText)
                ->setIsRead(false);

            $this->em->persist($notification);
        }
    }

    private function buildMessageNotificationText(User $sender, Message $message): string
    {
        $senderName = $this->buildDisplayName($sender);
        $hasAttachment = !$message->getAttachments()->isEmpty()
            || ($message->getFilePath() !== null && trim($message->getFilePath()) !== '');

        if ($hasAttachment) {
            return sprintf('%s a envoyé une pièce jointe', $senderName);
        }

        return sprintf('%s a envoyé un message', $senderName);
    }

    private function attachParticipant(Conversation $conversation, User $user, string $role): ConversationParticipant
    {
        $participant = (new ConversationParticipant())
            ->setConversation($conversation)
            ->setUser($user)
            ->setRole($role);

        $conversation->addParticipant($participant);
        $this->em->persist($participant);

        return $participant;
    }

    private function ensureParticipant(Conversation $conversation, User $user, string $role): void
    {
        $existing = $this->participantRepository->findForConversationAndUser($conversation, $user);
        if ($existing instanceof ConversationParticipant) {
            $existing
                ->setDeletedAt(null)
                ->setHiddenAt(null)
                ->setRole($role);

            return;
        }

        $this->attachParticipant($conversation, $user, $role);
    }

    /**
     * @param User[] $selectedParents
     * @return User[]
     */
    private function normalizeGroupMembers(User $creator, array $selectedParents): array
    {
        $members = [$creator];
        $seen = [];
        if ($creator->getId() !== null) {
            $seen[$creator->getId()] = true;
        }

        foreach ($selectedParents as $parent) {
            $parentId = $parent->getId();
            if ($parentId === null || isset($seen[$parentId])) {
                continue;
            }

            $seen[$parentId] = true;
            $members[] = $parent;
        }

        return $members;
    }

    private function buildDisplayName(?User $user): string
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

    public function resolveAttachmentAbsolutePath(MessageAttachment $attachment): string
    {
        $storagePath = trim($attachment->getStoragePath());
        if ($storagePath !== '') {
            if (str_starts_with($storagePath, '/')) {
                return $this->projectDir . '/public' . $storagePath;
            }

            return $storagePath;
        }

        return rtrim($this->chatUploadDir, '/\\') . DIRECTORY_SEPARATOR . $attachment->getStoredName();
    }

    /**
     * @return UploadedFile[]
     */
    private function normalizeUploadedFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $normalized[] = $file;
            }
        }

        return $normalized;
    }

    private function messageHasOnlyImages(Message $message): bool
    {
        $attachments = $message->getAttachments();
        if ($attachments->isEmpty()) {
            return false;
        }

        foreach ($attachments as $attachment) {
            if (!$attachment->isImage()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeAttachments(Message $message): array
    {
        $items = [];

        foreach ($message->getAttachments() as $attachment) {
            $attachmentId = $attachment->getId();
            if ($attachmentId === null) {
                continue;
            }

            $isAudio = $attachment->getType() === 'audio' || str_starts_with($attachment->getMimeType(), 'audio/');
            $url = $this->urlGenerator->generate(
                $isAudio ? 'chat_audio_stream' : 'chat_attachment_download',
                ['attachmentId' => $attachmentId]
            );
            $items[] = [
                'id' => $attachmentId,
                'name' => $attachment->getOriginalName(),
                'mimeType' => $attachment->getMimeType(),
                'size' => $attachment->getSize(),
                'isImage' => $attachment->isImage(),
                'type' => $attachment->getType(),
                'duration' => $attachment->getDuration(),
                'url' => $url,
                'downloadUrl' => $url . '?download=1',
            ];
        }

        if ($items !== []) {
            return $items;
        }

        // Backward compatibility for legacy single-file messages.
        $legacyPath = $message->getFilePath();
        if ($legacyPath !== null && $legacyPath !== '') {
            $fileName = basename($legacyPath);
            $isImage = $message->getType() === 'image';

            return [[
                'id' => null,
                'name' => $fileName,
                'mimeType' => $isImage ? 'image/*' : 'application/octet-stream',
                'size' => 0,
                'isImage' => $isImage,
                'type' => $isImage ? 'image' : 'file',
                'duration' => null,
                'url' => $legacyPath,
                'downloadUrl' => $legacyPath,
            ]];
        }

        return [];
    }

    /**
     * @param array<int, array<string, mixed>> $attachments
     */
    private function resolveSerializedMessageType(Message $message, array $attachments): string
    {
        if ($attachments !== []) {
            $containsOnlyImages = true;
            $containsOnlyAudio = true;
            foreach ($attachments as $attachment) {
                if (!($attachment['isImage'] ?? false)) {
                    $containsOnlyImages = false;
                }

                $attachmentType = (string) ($attachment['type'] ?? '');
                $mimeType = (string) ($attachment['mimeType'] ?? '');
                $isAudio = $attachmentType === 'audio' || str_starts_with($mimeType, 'audio/');
                if (!$isAudio) {
                    $containsOnlyAudio = false;
                }
            }

            if (trim((string) $message->getContent()) === '') {
                if ($containsOnlyImages) {
                    return 'image';
                }

                if ($containsOnlyAudio) {
                    return 'audio';
                }
            }

            return 'file';
        }

        return $message->getType();
    }

    /**
     * @return array{
     *     originalName:string,
     *     storedName:string,
     *     storagePath:string,
     *     mimeType:string,
     *     size:int,
     *     isImage:bool,
     *     type:string,
     *     duration:null
     * }
     */
    private function storeUploadedFile(UploadedFile $file, string $subDirectory = ''): array
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Invalid uploaded file.');
        }

        $size = (int) ($file->getSize() ?? 0);
        if ($size <= 0) {
            throw new \InvalidArgumentException('Uploaded file is empty.');
        }

        if ($size > $this->chatUploadMaxSizeBytes) {
            throw new \InvalidArgumentException(sprintf(
                'Attachment exceeds maximum allowed size (%d MB).',
                (int) ceil($this->chatUploadMaxSizeBytes / 1024 / 1024)
            ));
        }

        $mimeType = (string) $file->getMimeType();
        if ($mimeType === '' || !in_array($mimeType, $this->chatUploadAllowedMimeTypes, true)) {
            throw new \InvalidArgumentException(sprintf('File type "%s" is not allowed.', $mimeType ?: 'unknown'));
        }

        $targetDir = rtrim($this->chatUploadDir, '/\\');
        $normalizedSubDirectory = trim($subDirectory, '/\\');
        if ($normalizedSubDirectory !== '') {
            $targetDir .= DIRECTORY_SEPARATOR . $normalizedSubDirectory;
        }
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Unable to create chat upload directory.');
        }

        $storedName = sprintf('%s.%s', bin2hex(random_bytes(16)), $this->resolveSafeExtension($mimeType));

        try {
            $file->move($targetDir, $storedName);
        } catch (FileException $exception) {
            throw new \RuntimeException('Could not store uploaded file.', 0, $exception);
        }

        $publicPrefix = rtrim($this->chatUploadPublicPrefix, '/');
        if ($normalizedSubDirectory !== '') {
            $publicPrefix .= '/' . str_replace('\\', '/', $normalizedSubDirectory);
        }
        $storagePath = $publicPrefix . '/' . $storedName;

        return [
            'originalName' => trim((string) $file->getClientOriginalName()) !== ''
                ? trim((string) $file->getClientOriginalName())
                : $storedName,
            'storedName' => $storedName,
            'storagePath' => $storagePath,
            'mimeType' => $mimeType,
            'size' => $size,
            'isImage' => str_starts_with($mimeType, 'image/'),
            'type' => str_starts_with($mimeType, 'image/') ? 'image' : 'file',
            'duration' => null,
        ];
    }

    private function resolveSafeExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
            'audio/webm', 'video/webm' => 'webm',
            'audio/ogg' => 'ogg',
            'audio/mp4' => 'm4a',
            'audio/mpeg' => 'mp3',
            default => 'bin',
        };
    }

    private function publishMessageEvent(string $type, Conversation $conversation, Message $message): void
    {
        $event = [
            'type' => $type,
            'conversationId' => $conversation->getId(),
            'message' => $this->serializeMessage($message),
        ];
        $payload = json_encode($event, JSON_UNESCAPED_SLASHES);

        if (!is_string($payload)) {
            return;
        }

        $this->safePublish($this->getConversationTopic($conversation), $payload);
        $recipientUserIds = [];

        foreach ($conversation->getParticipants() as $participant) {
            if ($participant->getDeletedAt() !== null || $participant->getHiddenAt() !== null) {
                continue;
            }

            $user = $participant->getUser();
            if ($user === null) {
                continue;
            }

            $recipientUserIds[] = (int) $user->getId();
            $this->safePublish($this->getUserTopic($user), $payload);
        }

        $this->safePublishWebSocketBridge($event, $recipientUserIds);
    }

    private function publishConversationUpdate(Conversation $conversation): void
    {
        $event = [
            'type' => 'conversation.updated',
            'conversationId' => $conversation->getId(),
        ];
        $payload = json_encode($event, JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            return;
        }

        $recipientUserIds = [];
        foreach ($conversation->getParticipants() as $participant) {
            if ($participant->getDeletedAt() !== null || $participant->getHiddenAt() !== null) {
                continue;
            }

            $user = $participant->getUser();
            if ($user === null) {
                continue;
            }

            $recipientUserIds[] = (int) $user->getId();
            $this->safePublish($this->getUserTopic($user), $payload);
        }

        $this->safePublishWebSocketBridge($event, $recipientUserIds);
    }

    private function safePublish(string $topic, string $payload): void
    {
        try {
            $this->hub->publish(new Update($topic, $payload));
        } catch (\Throwable $e) {
            $this->logger->warning('Mercure publish failed', ['exception' => $e]);
        }
    }

    /**
     * @param int[] $recipientUserIds
     */
    private function safePublishWebSocketBridge(array $event, array $recipientUserIds): void
    {
        $bridgeUrl = trim($this->chatWebSocketBridgeUrl);
        if ($bridgeUrl === '' || $recipientUserIds === []) {
            return;
        }

        $event['recipientUserIds'] = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $recipientUserIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($event['recipientUserIds'] === []) {
            return;
        }

        $secret = trim($this->chatWebSocketBridgeSecret) !== ''
            ? trim($this->chatWebSocketBridgeSecret)
            : $this->kernelSecret;

        try {
            $response = $this->httpClient->request('POST', $bridgeUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Chat-Bridge-Secret' => $secret,
                ],
                'json' => $event,
                'timeout' => 0.35,
            ]);
            $response->getStatusCode();
        } catch (\Throwable $e) {
            $this->logger->warning('WebSocket bridge publish failed', ['exception' => $e]);
        }
    }
}
