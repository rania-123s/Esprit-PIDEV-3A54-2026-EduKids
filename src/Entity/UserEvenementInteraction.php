<?php

namespace App\Entity;

use App\Repository\Evenement\UserEvenementInteractionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserEvenementInteractionRepository::class)]
#[ORM\Table(name: 'user_evenement_interaction')]
#[ORM\UniqueConstraint(name: 'unique_user_evenement_type', columns: ['user_id', 'evenement_id', 'type_interaction'])]
class UserEvenementInteraction
{
    public const TYPE_LIKE = 'like';
    public const TYPE_DISLIKE = 'dislike';
    public const TYPE_FAVORITE = 'favorite';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Evenement::class)]
    #[ORM\JoinColumn(name: 'evenement_id', referencedColumnName: 'id_evenement', nullable: false, onDelete: 'CASCADE')]
    private ?Evenement $evenement = null;

    #[ORM\Column(name: 'type_interaction', length: 20)]
    private ?string $typeInteraction = null;

    #[ORM\Column(name: 'created_at')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): static
    {
        $this->evenement = $evenement;
        return $this;
    }

    public function getTypeInteraction(): ?string
    {
        return $this->typeInteraction;
    }

    public function setTypeInteraction(string $typeInteraction): static
    {
        $this->typeInteraction = $typeInteraction;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
