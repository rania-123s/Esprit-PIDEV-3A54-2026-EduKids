<?php

namespace App\Entity\Quiz;

use App\Repository\Quiz\QuizRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuizRepository::class)]
#[ORM\Table(name: 'quiz_quiz')]
class Quiz
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre du quiz est obligatoire.')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Le titre doit faire au moins {{ limit }} caractères.', maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\When(expression: 'value != null and value != ""', constraints: [
        new Assert\Length(min: 3, max: 5000, minMessage: 'La description doit faire au moins {{ limit }} caractères.', maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'),
    ])]
    private ?string $description = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\When(expression: 'value != null and value != ""', constraints: [
        new Assert\Length(min: 3, max: 500, minMessage: 'L\'URL de l\'image doit faire au moins {{ limit }} caractères.', maxMessage: 'L\'URL de l\'image ne peut pas dépasser {{ limit }} caractères.'),
    ])]
    private ?string $imageUrl = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $published = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $chatbotEnabled = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Question>
     */
    #[ORM\OneToMany(targetEntity: Question::class, mappedBy: 'quiz', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $questions;

    /**
     * @var Collection<int, QuizAttempt>
     */
    #[ORM\OneToMany(targetEntity: QuizAttempt::class, mappedBy: 'quiz', cascade: ['remove'])]
    private Collection $attempts;

    public function __construct()
    {
        $this->questions = new ArrayCollection();
        $this->attempts = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;
        return $this;
    }

    public function isChatbotEnabled(): bool
    {
        return $this->chatbotEnabled;
    }

    public function setChatbotEnabled(bool $chatbotEnabled): static
    {
        $this->chatbotEnabled = $chatbotEnabled;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, Question>
     */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(Question $question): static
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setQuiz($this);
        }
        return $this;
    }

    public function removeQuestion(Question $question): static
    {
        if ($this->questions->removeElement($question)) {
            if ($question->getQuiz() === $this) {
                $question->setQuiz(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, QuizAttempt>
     */
    public function getAttempts(): Collection
    {
        return $this->attempts;
    }
}
