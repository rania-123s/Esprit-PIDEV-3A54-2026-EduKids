<?php

namespace App\Entity;

use App\Repository\LeconRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeconRepository::class)]
class Lecon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lecons')]
    private ?Cours $cours = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column]
    private ?int $ordre = null;

    #[ORM\Column(length: 255)]
    private ?string $media_type = null;

    #[ORM\Column(length: 255)]
    private ?string $media_url = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function getMediaType(): ?string
    {
        return $this->media_type;
    }

    public function setMediaType(string $media_type): static
    {
        $this->media_type = $media_type;

        return $this;
    }

    public function getMediaUrl(): ?string
    {
        return $this->media_url;
    }

    public function setMediaUrl(string $media_url): static
    {
        $this->media_url = $media_url;

        return $this;
    }
}
