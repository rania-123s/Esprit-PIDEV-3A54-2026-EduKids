<?php

namespace App\Entity;

use App\Repository\RessourceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RessourceRepository::class)]
#[ORM\Table(name: 'ressource')]
class Ressource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_ressource')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'ressources')]
    #[ORM\JoinColumn(name: 'id_evenement', referencedColumnName: 'id_evenement', nullable: false)]
    #[Assert\NotNull(message: 'L\'événement est obligatoire.')]
    private ?Evenement $evenement = null;

    #[ORM\Column(name: 'type_ressource', length: 50)]
    #[Assert\NotBlank(message: 'Le type de ressource est obligatoire.')]
    #[Assert\Choice(
        choices: ['activite', 'pause', 'document', 'materiel'],
        message: 'Veuillez sélectionner un type de ressource valide.'
    )]
    private ?string $typeRessource = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(name: 'date_debut', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(name: 'date_fin', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Assert\GreaterThan(
        propertyPath: 'dateDebut',
        message: 'La date de fin doit être postérieure à la date de début.'
    )]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fichier = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'La quantité doit être un nombre positif ou zéro.')]
    private ?int $quantite = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTypeRessource(): ?string
    {
        return $this->typeRessource;
    }

    public function setTypeRessource(?string $typeRessource): static
    {
        $this->typeRessource = $typeRessource;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getFichier(): ?string
    {
        return $this->fichier;
    }

    public function setFichier(?string $fichier): static
    {
        $this->fichier = $fichier;

        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
