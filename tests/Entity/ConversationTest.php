<?php

namespace App\Tests\Entity;

use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Message;
use PHPUnit\Framework\TestCase;

final class ConversationTest extends TestCase
{
    public function testConstructorAndLifecycleCallbacks(): void
    {
        $conversation = new Conversation();

        self::assertInstanceOf(\DateTimeImmutable::class, $conversation->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $conversation->getUpdatedAt());
        self::assertCount(0, $conversation->getMessages());
        self::assertCount(0, $conversation->getParticipants());
        self::assertFalse($conversation->isGroup());

        $previousUpdatedAt = $conversation->getUpdatedAt();
        usleep(1000);
        $conversation->onPreUpdate();
        self::assertGreaterThanOrEqual(
            (int) $previousUpdatedAt->format('Uu'),
            (int) $conversation->getUpdatedAt()->format('Uu')
        );
    }

    public function testAddAndRemoveMessageUpdatesBothSides(): void
    {
        $conversation = new Conversation();
        $message = new Message();

        $updatedBeforeAdd = $conversation->getUpdatedAt();

        $conversation->addMessage($message);
        self::assertCount(1, $conversation->getMessages());
        self::assertSame($conversation, $message->getConversation());
        self::assertNotSame($updatedBeforeAdd, $conversation->getUpdatedAt());

        $conversation->removeMessage($message);
        self::assertCount(0, $conversation->getMessages());
        self::assertNull($message->getConversation());
    }

    public function testAddAndRemoveParticipantUpdatesBothSides(): void
    {
        $conversation = new Conversation();
        $participant = new ConversationParticipant();

        $conversation->addParticipant($participant);
        self::assertCount(1, $conversation->getParticipants());
        self::assertSame($conversation, $participant->getConversation());

        $conversation->removeParticipant($participant);
        self::assertCount(0, $conversation->getParticipants());
        self::assertNull($participant->getConversation());
    }
}
