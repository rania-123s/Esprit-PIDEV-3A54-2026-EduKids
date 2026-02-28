<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(
    name: 'notification',
    indexes: [
        new ORM\Index(name: 'idx_notification_receiver_is_read_created', columns: ['receiver_id', 'is_read', 'created_at']),
        new ORM\Index(name: 'idx_notification_created_at', columns: ['created_at']),
        new ORM\Index(name: 'idx_notification_conversation', columns: ['conversation_id']),
        new ORM\Index(name: 'idx_notification_sender', columns: ['sender_id']),
    ]
)]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    public const TYPE_MESSAGE = 'message';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $receiver = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $sender = null;

    #[ORM\Column(length: 30, options: ['default' => self::TYPE_MESSAGE])]
    private string $type = self::TYPE_MESSAGE;

    #[ORM\Column]
    private ?int $conversationId = null;

    #[ORM\Column(length: 255)]
    private string $text = '';

    #[ORM\Column(options: ['default' => false])]
    private bool $isRead = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReceiver(): ?User
    {
        return $this->receiver;
    }

    public function setReceiver(?User $receiver): static
    {
        $this->receiver = $receiver;

        return $this;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): static
    {
        $this->sender = $sender;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = trim($type) !== '' ? trim($type) : self::TYPE_MESSAGE;

        return $this;
    }

    public function getConversationId(): ?int
    {
        return $this->conversationId;
    }

    public function setConversationId(?int $conversationId): static
    {
        $this->conversationId = $conversationId !== null ? max(0, $conversationId) : null;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = trim($text);

        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
