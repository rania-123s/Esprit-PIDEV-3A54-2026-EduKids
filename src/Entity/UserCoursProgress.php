<?php

namespace App\Entity;

use App\Repository\UserCoursProgressRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserCoursProgressRepository::class)]
#[ORM\Table(name: 'user_cours_progress', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'UNIQ_USER_COURS_PROGRESS_USER_COURS', columns: ['user_id', 'cours_id']),
])]
class UserCoursProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userCoursProgress')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'progress')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Cours $cours = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $progress = 0;

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

    public function getCours(): ?Cours
    {
        return $this->cours;
    }

    public function setCours(?Cours $cours): static
    {
        $this->cours = $cours;

        return $this;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function setProgress(int $progress): static
    {
        $this->progress = max(0, min(100, $progress));

        return $this;
    }
}
