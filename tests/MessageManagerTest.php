<?php

namespace App\Tests\Service;

use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\ConversationParticipantRepository;
use App\Repository\ConversationRepository;
use App\Repository\UserRepository;
use App\Service\ChatService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MessageManagerTest extends TestCase
{
    private function makeService(int $maxFilesPerMessage = 3): ChatService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $convRepo = $this->createMock(ConversationRepository::class);
        $participantRepo = $this->createMock(ConversationParticipantRepository::class);
        $userRepo = $this->createMock(UserRepository::class);
        $hub = $this->createMock(HubInterface::class);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        return new ChatService(
            $em,
            $convRepo,
            $participantRepo,
            $userRepo,
            $hub,
            $httpClient,
            $urlGenerator,
            projectDir: '/tmp',
            chatUploadDir: '/tmp/uploads',
            chatUploadPublicPrefix: '/uploads',
            chatUploadMaxSizeBytes: 10_000_000,
            chatUploadMaxFilesPerMessage: $maxFilesPerMessage,
            chatUploadAllowedMimeTypes: ['image/png', 'image/jpeg', 'application/pdf'],
            chatAutoReplyAdminEmail: 'admin@test.com',
            chatAutoReplyCooldownMinutes: 0,
            chatWebSocketBridgeUrl: '',
            chatWebSocketBridgeSecret: '',
            kernelSecret: 'secret',
            logger: new NullLogger()
        );
    }

    public function testCreateMessageThrowsWhenEmptyContentAndNoFiles(): void
    {
        $service = $this->makeService();

        $conversation = $this->createMock(Conversation::class);
        $sender = $this->createMock(User::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty message.');

        // content = vide, files = []
        $service->createMessage($conversation, $sender, '   ', []);
    }

    public function testCreateMessageThrowsWhenTooManyAttachments(): void
    {
        $service = $this->makeService(maxFilesPerMessage: 2);

        $conversation = $this->createMock(Conversation::class);
        $sender = $this->createMock(User::class);

        // On met 3 éléments "non UploadedFile" => normalizeUploadedFiles() va les ignorer
        // Donc ici il faut de VRAIS UploadedFile si on veut dépasser la limite.
        // Solution unitaire : on teste plutôt en envoyant 3 UploadedFile mockés.
        $file1 = $this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class);
        $file2 = $this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class);
        $file3 = $this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Too many attachments');

        $service->createMessage($conversation, $sender, 'Hello', [$file1, $file2, $file3]);
    }
}
