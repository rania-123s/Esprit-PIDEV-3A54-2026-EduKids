<?php

namespace App\Entity;

use App\Repository\QuestionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    private ?Quiz $quiz = null;

    #[ORM\Column(length: 255)]
    private ?string $enonce = null;

    #[ORM\Column(length: 255)]
    private ?string $bonne_reponse = null;

    #[ORM\Column]
    private ?int $choix = null;

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

    public function getEnonce(): ?string
    {
        return $this->enonce;
    }

    public function setEnonce(string $enonce): static
    {
        $this->enonce = $enonce;

        return $this;
    }

    public function getBonneReponse(): ?string
    {
        return $this->bonne_reponse;
    }

    public function setBonneReponse(string $bonne_reponse): static
    {
        $this->bonne_reponse = $bonne_reponse;

        return $this;
    }

    public function getChoix(): ?int
    {
        return $this->choix;
    }

    public function setChoix(int $choix): static
    {
        $this->choix = $choix;

        return $this;
    }
}
