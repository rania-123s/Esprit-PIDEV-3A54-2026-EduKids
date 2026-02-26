<?php

namespace App\Entity\Quiz;

use App\Repository\Quiz\QuestionOptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuestionOptionRepository::class)]
#[ORM\Table(name: 'quiz_question_option')]
class QuestionOption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Question::class, inversedBy: 'questionOptions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Question $question = null;

    #[ORM\Column(length: 1000)]
    #[Assert\NotBlank(message: 'Le texte de l\'option est obligatoire.')]
    #[Assert\Length(min: 3, max: 1000, minMessage: 'Le texte de l\'option doit faire au moins {{ limit }} caractères.', maxMessage: 'L\'option ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $texte = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $ordre = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $correct = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function setQuestion(?Question $question): static
    {
        $this->question = $question;
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

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
        return $this;
    }

    public function isCorrect(): bool
    {
        return $this->correct;
    }

    public function setCorrect(bool $correct): static
    {
        $this->correct = $correct;
        return $this;
    }
}
