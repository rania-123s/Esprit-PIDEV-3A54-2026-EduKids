<?php

namespace App\Entity;

use App\Repository\Evenement\EvenementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
#[ORM\Table(name: 'evenement')]
class Evenement
{
    public const TYPES = [
        'Sport' => 'sport',
        'Art' => 'art',
        'Musique' => 'musique',
        'Éducation' => 'education',
        'Science' => 'science',
        'Technologie' => 'technologie',
        'Culture' => 'culture',
        'Sortie' => 'sortie',
        'Atelier' => 'atelier',
        'Fête' => 'fete',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_evenement')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        min: 10,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(name: 'date_evenement', type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date de l\'événement est obligatoire.')]
    #[Assert\GreaterThanOrEqual(
        value: 'today',
        message: 'La date de l\'événement doit être aujourd\'hui ou dans le futur.',
    )]
    private ?\DateTimeInterface $dateEvenement = null;

    #[ORM\Column(name: 'heure_debut', type: Types::TIME_MUTABLE)]
    #[Assert\NotNull(message: 'L\'heure de début est obligatoire.')]
    private ?\DateTimeInterface $heureDebut = null;

    #[ORM\Column(name: 'heure_fin', type: Types::TIME_MUTABLE)]
    #[Assert\NotNull(message: 'L\'heure de fin est obligatoire.')]
    #[Assert\GreaterThan(
        propertyPath: 'heureDebut',
        message: 'L\'heure de fin doit être après l\'heure de début.'
    )]
    private ?\DateTimeInterface $heureFin = null;

    #[ORM\Column(name: 'type_evenement', length: 50, nullable: true)]
    private ?string $typeEvenement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $localisation = null;

    #[ORM\OneToOne(mappedBy: 'evenement', cascade: ['persist', 'remove'])]
    private ?Programme $programme = null;

    #[ORM\Column(name: 'likes_count', type: 'integer', options: ['default' => 0])]
    private int $likesCount = 0;

    #[ORM\Column(name: 'dislikes_count', type: 'integer', options: ['default' => 0])]
    private int $dislikesCount = 0;

    #[ORM\Column(name: 'favorites_count', type: 'integer', options: ['default' => 0])]
    private int $favoritesCount = 0;

    #[ORM\Column(name: 'nb_places_disponibles', type: 'integer', nullable: true)]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le nombre de places disponibles doit être positif ou nul.')]
    private ?int $nbPlacesDisponibles = null;

    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'evenement', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $reservations;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = $titre;
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

    public function getDateEvenement(): ?\DateTimeInterface
    {
        return $this->dateEvenement;
    }

    public function setDateEvenement(?\DateTimeInterface $dateEvenement): static
    {
        $this->dateEvenement = $dateEvenement;
        return $this;
    }

    public function getHeureDebut(): ?\DateTimeInterface
    {
        return $this->heureDebut;
    }

    public function setHeureDebut(?\DateTimeInterface $heureDebut): static
    {
        $this->heureDebut = $heureDebut;
        return $this;
    }

    public function getHeureFin(): ?\DateTimeInterface
    {
        return $this->heureFin;
    }

    public function setHeureFin(?\DateTimeInterface $heureFin): static
    {
        $this->heureFin = $heureFin;
        return $this;
    }

    /**
     * Retourne la durée de l'événement en minutes
     */
    public function getDuree(): ?int
    {
        if ($this->heureDebut && $this->heureFin) {
            $diff = $this->heureDebut->diff($this->heureFin);
            return ($diff->h * 60) + $diff->i;
        }
        return null;
    }

    /**
     * Retourne la durée formatée (ex: "2h30")
     */
    public function getDureeFormatee(): string
    {
        $duree = $this->getDuree();
        if ($duree === null) {
            return '-';
        }
        $heures = intdiv($duree, 60);
        $minutes = $duree % 60;
        if ($heures > 0 && $minutes > 0) {
            return $heures . 'h' . str_pad($minutes, 2, '0', STR_PAD_LEFT);
        } elseif ($heures > 0) {
            return $heures . 'h';
        } else {
            return $minutes . 'min';
        }
    }

    public function getTypeEvenement(): ?string
    {
        return $this->typeEvenement;
    }

    public function setTypeEvenement(?string $typeEvenement): static
    {
        $this->typeEvenement = $typeEvenement;
        return $this;
    }

    public function getTypeEvenementLabel(): string
    {
        return array_search($this->typeEvenement, self::TYPES) ?: $this->typeEvenement ?? '';
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(?string $localisation): static
    {
        $this->localisation = $localisation;
        return $this;
    }

    public function getProgramme(): ?Programme
    {
        return $this->programme;
    }

    public function setProgramme(?Programme $programme): static
    {
        // Unset the owning side of the relation if necessary
        if ($programme === null && $this->programme !== null) {
            $this->programme->setEvenement(null);
        }

        // Set the owning side of the relation if necessary
        if ($programme !== null && $programme->getEvenement() !== $this) {
            $programme->setEvenement($this);
        }

        $this->programme = $programme;
        return $this;
    }

    public function __toString(): string
    {
        return $this->titre ?? '';
    }

    public function getLikesCount(): int
    {
        return $this->likesCount;
    }

    public function setLikesCount(int $likesCount): static
    {
        $this->likesCount = $likesCount;
        return $this;
    }

    public function incrementLikes(): static
    {
        $this->likesCount++;
        return $this;
    }

    public function decrementLikes(): static
    {
        if ($this->likesCount > 0) {
            $this->likesCount--;
        }
        return $this;
    }

    public function getDislikesCount(): int
    {
        return $this->dislikesCount;
    }

    public function setDislikesCount(int $dislikesCount): static
    {
        $this->dislikesCount = $dislikesCount;
        return $this;
    }

    public function incrementDislikes(): static
    {
        $this->dislikesCount++;
        return $this;
    }

    public function decrementDislikes(): static
    {
        if ($this->dislikesCount > 0) {
            $this->dislikesCount--;
        }
        return $this;
    }

    public function getFavoritesCount(): int
    {
        return $this->favoritesCount;
    }

    public function setFavoritesCount(int $favoritesCount): static
    {
        $this->favoritesCount = $favoritesCount;
        return $this;
    }

    public function incrementFavorites(): static
    {
        $this->favoritesCount++;
        return $this;
    }

    public function decrementFavorites(): static
    {
        if ($this->favoritesCount > 0) {
            $this->favoritesCount--;
        }
        return $this;
    }

    /**
     * Vérifie si l'événement a un programme associé
     */
    public function hasProgramme(): bool
    {
        return $this->programme !== null;
    }

    public function getNbPlacesDisponibles(): ?int
    {
        return $this->nbPlacesDisponibles;
    }

    public function setNbPlacesDisponibles(?int $nbPlacesDisponibles): static
    {
        $this->nbPlacesDisponibles = $nbPlacesDisponibles;
        return $this;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setEvenement($this);
        }
        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        if ($this->reservations->removeElement($reservation)) {
            if ($reservation->getEvenement() === $this) {
                $reservation->setEvenement(null);
            }
        }
        return $this;
    }

    /**
     * Calcule le nombre de places réservées pour cet événement
     */
    public function getNbPlacesReservees(): int
    {
        $total = 0;
        foreach ($this->reservations as $reservation) {
            $total += $reservation->getNbAdultes() + $reservation->getNbEnfants();
        }
        return $total;
    }

    /**
     * Vérifie si des places sont encore disponibles
     */
    public function hasPlacesDisponibles(): bool
    {
        if ($this->nbPlacesDisponibles === null) {
            return true; // Pas de limite de places
        }
        return $this->getNbPlacesReservees() < $this->nbPlacesDisponibles;
    }

    /**
     * Calcule le nombre de places restantes pour cet événement
     * @return int|null Retourne null si les places sont illimitées, sinon le nombre de places restantes
     */
    public function getNbPlacesRestantes(): ?int
    {
        if ($this->nbPlacesDisponibles === null) {
            return null; // Places illimitées
        }
        
        $nbPlacesReservees = $this->getNbPlacesReservees();
        $nbPlacesRestantes = $this->nbPlacesDisponibles - $nbPlacesReservees;
        
        // S'assurer que le résultat n'est pas négatif
        return max(0, $nbPlacesRestantes);
    }
}
