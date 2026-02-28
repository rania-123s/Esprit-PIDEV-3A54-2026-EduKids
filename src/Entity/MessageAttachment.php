<?php

namespace App\Entity;

use App\Repository\MessageAttachmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageAttachmentRepository::class)]
#[ORM\Table(
    name: 'message_attachment',
    indexes: [
        new ORM\Index(name: 'idx_message_attachment_message', columns: ['message_id']),
        new ORM\Index(name: 'idx_message_attachment_created_at', columns: ['created_at']),
        new ORM\Index(name: 'idx_message_attachment_type', columns: ['type']),
    ]
)]
#[ORM\HasLifecycleCallbacks]
class MessageAttachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Message::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Message $message = null;

    #[ORM\Column(length: 255)]
    private string $originalName = '';

    #[ORM\Column(length: 128)]
    private string $storedName = '';

    #[ORM\Column(length: 255)]
    private string $storagePath = '';

    #[ORM\Column(length: 120)]
    private string $mimeType = 'application/octet-stream';

    #[ORM\Column(type: 'bigint')]
    private string $size = '0';

    #[ORM\Column(options: ['default' => false])]
    private bool $isImage = false;

    #[ORM\Column(length: 20, options: ['default' => 'file'])]
    private string $type = 'file';

    #[ORM\Column(nullable: true)]
    private ?int $duration = null;

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

    public function getMessage(): ?Message
    {
        return $this->message;
    }

    public function setMessage(?Message $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getStoredName(): string
    {
        return $this->storedName;
    }

    public function setStoredName(string $storedName): static
    {
        $this->storedName = $storedName;

        return $this;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function setStoragePath(string $storagePath): static
    {
        $this->storagePath = $storagePath;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): int
    {
        return (int) $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = (string) max(0, $size);

        return $this;
    }

    public function isImage(): bool
    {
        return $this->isImage;
    }

    public function setIsImage(bool $isImage): static
    {
        $this->isImage = $isImage;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration !== null ? max(0, $duration) : null;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
