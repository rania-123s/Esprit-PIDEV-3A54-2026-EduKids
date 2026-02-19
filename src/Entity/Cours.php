<?php

namespace App\Entity;

use App\Repository\CoursRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CoursRepository::class)]
class Cours
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $niveau = null;

    #[ORM\Column(length: 255)]
    private ?string $matiere = null;

    #[ORM\Column(length: 255)]
    private ?string $image = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $likes = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $dislikes = 0;

    /**
     * @var Collection<int, Lecon>
     */
    #[ORM\OneToMany(targetEntity: Lecon::class, mappedBy: 'cours')]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $lecons;

    /**
     * @var Collection<int, Quiz>
     */
    #[ORM\OneToMany(targetEntity: Quiz::class, mappedBy: 'cours')]
    private Collection $quizzes;

    /**
     * @var Collection<int, UserCoursProgress>
     */
    #[ORM\OneToMany(targetEntity: UserCoursProgress::class, mappedBy: 'cours', orphanRemoval: true)]
    private Collection $progress;

    public function __construct()
    {
        $this->lecons = new ArrayCollection();
        $this->quizzes = new ArrayCollection();
        $this->progress = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getNiveau(): ?int
    {
        return $this->niveau;
    }

    public function setNiveau(int $niveau): static
    {
        $this->niveau = $niveau;

        return $this;
    }

    public function getMatiere(): ?string
    {
        return $this->matiere;
    }

    public function setMatiere(string $matiere): static
    {
        $this->matiere = $matiere;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getLikes(): int
    {
        return $this->likes;
    }

    public function setLikes(int $likes): static
    {
        $this->likes = max(0, $likes);

        return $this;
    }

    public function getDislikes(): int
    {
        return $this->dislikes;
    }

    public function setDislikes(int $dislikes): static
    {
        $this->dislikes = max(0, $dislikes);

        return $this;
    }

    /**
     * @return Collection<int, Lecon>
     */
    public function getLecons(): Collection
    {
        return $this->lecons;
    }

    public function addLecon(Lecon $lecon): static
    {
        if (!$this->lecons->contains($lecon)) {
            $this->lecons->add($lecon);
            $lecon->setCours($this);
        }

        return $this;
    }

    public function removeLecon(Lecon $lecon): static
    {
        if ($this->lecons->removeElement($lecon)) {
            // set the owning side to null (unless already changed)
            if ($lecon->getCours() === $this) {
                $lecon->setCours(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Quiz>
     */
    public function getQuizzes(): Collection
    {
        return $this->quizzes;
    }

    public function addQuiz(Quiz $quiz): static
    {
        if (!$this->quizzes->contains($quiz)) {
            $this->quizzes->add($quiz);
            $quiz->setCours($this);
        }

        return $this;
    }

    public function removeQuiz(Quiz $quiz): static
    {
        if ($this->quizzes->removeElement($quiz)) {
            // set the owning side to null (unless already changed)
            if ($quiz->getCours() === $this) {
                $quiz->setCours(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserCoursProgress>
     */
    public function getProgress(): Collection
    {
        return $this->progress;
    }

    public function addProgress(UserCoursProgress $progress): static
    {
        if (!$this->progress->contains($progress)) {
            $this->progress->add($progress);
            $progress->setCours($this);
        }

        return $this;
    }

    public function removeProgress(UserCoursProgress $progress): static
    {
        if ($this->progress->removeElement($progress)) {
            if ($progress->getCours() === $this) {
                $progress->setCours(null);
            }
        }

        return $this;
    }
}
