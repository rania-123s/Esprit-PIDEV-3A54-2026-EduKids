<?php

namespace App\Entity;

use App\Repository\Evenement\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservation')]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Evenement::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(name: 'id_evenement', referencedColumnName: 'id_evenement', nullable: false)]
    private ?Evenement $evenement = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    private ?string $prenom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'L\'email n\'est pas valide.')]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(name: 'nb_adultes', type: 'integer', options: ['default' => 0])]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le nombre d\'adultes doit être positif ou nul.')]
    private int $nbAdultes = 0;

    #[ORM\Column(name: 'nb_enfants', type: 'integer', options: ['default' => 0])]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le nombre d\'enfants doit être positif ou nul.')]
    private int $nbEnfants = 0;

    #[ORM\Column(name: 'date_reservation', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateReservation = null;

    public function __construct()
    {
        $this->dateReservation = new \DateTime();
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getNbAdultes(): int
    {
        return $this->nbAdultes;
    }

    public function setNbAdultes(int $nbAdultes): static
    {
        $this->nbAdultes = $nbAdultes;
        return $this;
    }

    public function getNbEnfants(): int
    {
        return $this->nbEnfants;
    }

    public function setNbEnfants(int $nbEnfants): static
    {
        $this->nbEnfants = $nbEnfants;
        return $this;
    }

    public function getDateReservation(): ?\DateTimeInterface
    {
        return $this->dateReservation;
    }

    public function setDateReservation(\DateTimeInterface $dateReservation): static
    {
        $this->dateReservation = $dateReservation;
        return $this;
    }

    /**
     * Calcule le nombre total de places réservées
     */
    public function getNbPlacesTotal(): int
    {
        return $this->nbAdultes + $this->nbEnfants;
    }
}
