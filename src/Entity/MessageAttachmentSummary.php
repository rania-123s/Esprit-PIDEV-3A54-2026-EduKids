<?php

namespace App\Entity;

use App\Repository\MessageAttachmentSummaryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageAttachmentSummaryRepository::class)]
#[ORM\Table(
    name: 'message_attachment_summary',
    indexes: [
        new ORM\Index(name: 'idx_mas_attachment', columns: ['attachment_id']),
        new ORM\Index(name: 'idx_mas_user', columns: ['user_id']),
        new ORM\Index(name: 'idx_mas_status', columns: ['status']),
        new ORM\Index(name: 'idx_mas_created_at', columns: ['created_at']),
    ],
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_mas_attachment_user', columns: ['attachment_id', 'user_id']),
    ]
)]
#[ORM\HasLifecycleCallbacks]
class MessageAttachmentSummary
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DONE = 'done';
    public const STATUS_ERROR = 'error';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MessageAttachment::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?MessageAttachment $attachment = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'text')]
    private string $summaryText = '';

    #[ORM\Column(length: 16, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt ??= $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAttachment(): ?MessageAttachment
    {
        return $this->attachment;
    }

    public function setAttachment(?MessageAttachment $attachment): static
    {
        $this->attachment = $attachment;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getSummaryText(): string
    {
        return $this->summaryText;
    }

    public function setSummaryText(string $summaryText): static
    {
        $this->summaryText = trim($summaryText);

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $normalized = strtolower(trim($status));
        if (!in_array($normalized, [self::STATUS_PENDING, self::STATUS_DONE, self::STATUS_ERROR], true)) {
            $normalized = self::STATUS_PENDING;
        }

        $this->status = $normalized;

        return $this;
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage !== null ? trim($errorMessage) : null;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
