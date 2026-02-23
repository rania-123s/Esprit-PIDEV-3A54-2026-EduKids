<?php

namespace App\Entity;

use App\Repository\CoursRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CoursRepository::class)]
class Cours
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire.")]
    #[Assert\Length(max: 255, maxMessage: "Le titre ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $titre = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "La description est obligatoire.")]
    #[Assert\Length(max: 255, maxMessage: "La description ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Le niveau est obligatoire.")]
    #[Assert\Positive(message: "Le niveau doit être un nombre positif.")]
    private ?int $niveau = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "La matière est obligatoire.")]
    #[Assert\Length(max: 255, maxMessage: "La matière ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $matiere = null;

    #[ORM\Column(length: 255)]
    private ?string $image = null;

    /**
     * @var Collection<int, Lecon>
     */
    #[ORM\OneToMany(targetEntity: Lecon::class, mappedBy: 'cours')]
    private Collection $lecons;

    /**
     * @var Collection<int, Quiz>
     */
    #[ORM\OneToMany(targetEntity: Quiz::class, mappedBy: 'cours')]
    private Collection $quizzes;

    public function __construct()
    {
        $this->lecons = new ArrayCollection();
        $this->quizzes = new ArrayCollection();
    }

    // ------------------- Getters et Setters -------------------

    public function getId(): ?int { return $this->id; }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }

    public function getNiveau(): ?int { return $this->niveau; }
    public function setNiveau(int $niveau): static { $this->niveau = $niveau; return $this; }

    public function getMatiere(): ?string { return $this->matiere; }
    public function setMatiere(string $matiere): static { $this->matiere = $matiere; return $this; }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }

    /**
     * @return Collection<int, Lecon>
     */
    public function getLecons(): Collection { return $this->lecons; }

    public function addLecon(Lecon $lecon): static {
        if (!$this->lecons->contains($lecon)) {
            $this->lecons->add($lecon);
            $lecon->setCours($this);
        }
        return $this;
    }

    public function removeLecon(Lecon $lecon): static {
        if ($this->lecons->removeElement($lecon) && $lecon->getCours() === $this) {
            $lecon->setCours(null);
        }
        return $this;
    }

    /**
     * @return Collection<int, Quiz>
     */
    public function getQuizzes(): Collection { return $this->quizzes; }

    public function addQuiz(Quiz $quiz): static {
        if (!$this->quizzes->contains($quiz)) {
            $this->quizzes->add($quiz);
            $quiz->setCours($this);
        }
        return $this;
    }

    public function removeQuiz(Quiz $quiz): static {
        if ($this->quizzes->removeElement($quiz) && $quiz->getCours() === $this) {
            $quiz->setCours(null);
        }
        return $this;
    }
}
