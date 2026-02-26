<?php

namespace App\Entity\Quiz;

use App\Repository\Quiz\QuestionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
#[ORM\Table(name: 'quiz_question')]
class Question
{
    public const TYPE_QCM = 'qcm';
    public const TYPE_TEXTE = 'texte';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Quiz::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Quiz $quiz = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'L\'énoncé de la question est obligatoire.')]
    #[Assert\Length(min: 3, max: 2000, minMessage: 'L\'énoncé doit faire au moins {{ limit }} caractères.', maxMessage: 'L\'énoncé ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $texte = null;

    /**
     * Type: qcm = multiple choice (use questionOptions), texte = free text answer (use bonneReponse).
     */
    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::TYPE_QCM, self::TYPE_TEXTE], message: 'Type invalide.')]
    private string $type = self::TYPE_QCM;

    /**
     * For TYPE_TEXTE only: expected answer string.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\When(expression: 'value != null and value != ""', constraints: [
        new Assert\Length(min: 3, max: 2000, minMessage: 'La réponse attendue doit faire au moins {{ limit }} caractères.', maxMessage: 'La réponse attendue ne peut pas dépasser {{ limit }} caractères.'),
    ])]
    private ?string $bonneReponse = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\PositiveOrZero(message: 'L\'ordre doit être un entier positif ou zéro.')]
    private int $ordre = 0;

    /**
     * @var Collection<int, QuestionOption>
     */
    #[ORM\OneToMany(targetEntity: QuestionOption::class, mappedBy: 'question', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC', 'id' => 'ASC'])]
    private Collection $questionOptions;

    public function __construct()
    {
        $this->questionOptions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuiz(): ?Quiz
    {
        return $this->quiz;
    }

    public function setQuiz(?Quiz $quiz): static
    {
        $this->quiz = $quiz;
        return $this;
    }

    public function getTexte(): ?string
    {
        return $this->texte;
    }

    public function setTexte(string $texte): static
    {
        $this->texte = $texte;
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

    public function getBonneReponse(): ?string
    {
        return $this->bonneReponse;
    }

    public function setBonneReponse(?string $bonneReponse): static
    {
        $this->bonneReponse = $bonneReponse;
        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
        return $this;
    }

    /**
     * @return Collection<int, QuestionOption>
     */
    public function getQuestionOptions(): Collection
    {
        return $this->questionOptions;
    }

    public function addQuestionOption(QuestionOption $option): static
    {
        if (!$this->questionOptions->contains($option)) {
            $this->questionOptions->add($option);
            $option->setQuestion($this);
        }
        return $this;
    }

    public function removeQuestionOption(QuestionOption $option): static
    {
        if ($this->questionOptions->removeElement($option)) {
            if ($option->getQuestion() === $this) {
                $option->setQuestion(null);
            }
        }
        return $this;
    }

    /**
     * Returns option texts as array (ordered). For templates that expect question.options.
     * @return string[]
     */
    public function getOptions(): array
    {
        $out = [];
        foreach ($this->questionOptions as $opt) {
            $out[] = $opt->getTexte() ?? '';
        }
        return $out;
    }

    /**
     * For QCM: 0-based index of the correct option. Returns null if none or not QCM.
     */
    public function getCorrectOptionIndex(): ?int
    {
        $index = 0;
        foreach ($this->questionOptions as $opt) {
            if ($opt->isCorrect()) {
                return $index;
            }
            $index++;
        }
        return null;
    }

    /**
     * For QCM: the QuestionOption marked as correct. Returns null if none.
     */
    public function getCorrectOption(): ?QuestionOption
    {
        foreach ($this->questionOptions as $opt) {
            if ($opt->isCorrect()) {
                return $opt;
            }
        }
        return null;
    }
}
