<?php

namespace App\Entity;

use App\Repository\ProgrammeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ProgrammeRepository::class)]
#[ORM\Table(name: 'programme')]
#[UniqueEntity(fields: ['evenement'], message: 'Ce programme est déjà associé à un événement.')]
#[Assert\Callback('validatePauseInterval')]
class Programme
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'programme', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'evenement_id', referencedColumnName: 'id_evenement', nullable: false, unique: true)]
    #[Assert\NotNull(message: 'L\'événement est obligatoire.')]
    private ?Evenement $evenement = null;

    #[ORM\Column(name: 'pause_debut', type: Types::TIME_MUTABLE)]
    #[Assert\NotNull(message: 'L\'heure de début de pause est obligatoire.')]
    private ?\DateTimeInterface $pauseDebut = null;

    #[ORM\Column(name: 'pause_fin', type: Types::TIME_MUTABLE)]
    #[Assert\NotNull(message: 'L\'heure de fin de pause est obligatoire.')]
    #[Assert\GreaterThan(
        propertyPath: 'pauseDebut',
        message: 'L\'heure de fin de pause doit être après l\'heure de début.'
    )]
    private ?\DateTimeInterface $pauseFin = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Les activités sont obligatoires.')]
    #[Assert\Length(
        min: 10,
        minMessage: 'Les activités doivent contenir au moins {{ limit }} caractères.'
    )]
    private ?string $activites = null;

    #[ORM\Column(name: 'documents_requis', type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Les documents requis sont obligatoires.')]
    private ?string $documentsRequis = null;

    #[ORM\Column(name: 'materiels_requis', type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Les matériels requis sont obligatoires.')]
    private ?string $materielsRequis = null;

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

    public function getPauseDebut(): ?\DateTimeInterface
    {
        return $this->pauseDebut;
    }

    public function setPauseDebut(?\DateTimeInterface $pauseDebut): static
    {
        $this->pauseDebut = $pauseDebut;
        return $this;
    }

    public function getPauseFin(): ?\DateTimeInterface
    {
        return $this->pauseFin;
    }

    public function setPauseFin(?\DateTimeInterface $pauseFin): static
    {
        $this->pauseFin = $pauseFin;
        return $this;
    }

    public function getActivites(): ?string
    {
        return $this->activites;
    }

    public function setActivites(?string $activites): static
    {
        $this->activites = $activites;
        return $this;
    }

    public function getDocumentsRequis(): ?string
    {
        return $this->documentsRequis;
    }

    public function setDocumentsRequis(?string $documentsRequis): static
    {
        $this->documentsRequis = $documentsRequis;
        return $this;
    }

    public function getMaterielsRequis(): ?string
    {
        return $this->materielsRequis;
    }

    public function setMaterielsRequis(?string $materielsRequis): static
    {
        $this->materielsRequis = $materielsRequis;
        return $this;
    }

    /**
     * Retourne la durée de la pause en minutes
     */
    public function getDureePause(): ?int
    {
        if ($this->pauseDebut && $this->pauseFin) {
            $diff = $this->pauseDebut->diff($this->pauseFin);
            return ($diff->h * 60) + $diff->i;
        }
        return null;
    }

    public function __toString(): string
    {
        return $this->evenement ? 'Programme de ' . $this->evenement->getTitre() : 'Programme #' . $this->id;
    }

    /**
     * Valide que la pause est dans l'intervalle de l'événement
     */
    public function validatePauseInterval(ExecutionContextInterface $context): void
    {
        if (!$this->evenement) {
            return; // La validation de l'événement obligatoire sera gérée par une autre contrainte
        }

        if (!$this->pauseDebut || !$this->pauseFin) {
            return; // Les validations de champs obligatoires seront gérées par d'autres contraintes
        }

        $evenement = $this->evenement;
        
        if (!$evenement->getHeureDebut() || !$evenement->getHeureFin()) {
            $context->buildViolation('L\'événement doit avoir des heures de début et de fin définies pour valider la pause.')
                ->atPath('evenement')
                ->addViolation();
            return;
        }

        // Convertir les heures en minutes depuis minuit pour faciliter la comparaison
        $pauseDebutMinutes = $this->pauseDebut->format('H') * 60 + (int)$this->pauseDebut->format('i');
        $pauseFinMinutes = $this->pauseFin->format('H') * 60 + (int)$this->pauseFin->format('i');
        $evenementDebutMinutes = $evenement->getHeureDebut()->format('H') * 60 + (int)$evenement->getHeureDebut()->format('i');
        $evenementFinMinutes = $evenement->getHeureFin()->format('H') * 60 + (int)$evenement->getHeureFin()->format('i');

        // Vérifier que le début de la pause est après ou égal au début de l'événement
        if ($pauseDebutMinutes < $evenementDebutMinutes) {
            $context->buildViolation(
                'L\'heure de début de pause ({{ pause_debut }}) doit être après ou égale à l\'heure de début de l\'événement ({{ evenement_debut }}).'
            )
                ->setParameter('{{ pause_debut }}', $this->pauseDebut->format('H:i'))
                ->setParameter('{{ evenement_debut }}', $evenement->getHeureDebut()->format('H:i'))
                ->atPath('pauseDebut')
                ->addViolation();
        }

        // Vérifier que la fin de la pause est avant ou égale à la fin de l'événement
        if ($pauseFinMinutes > $evenementFinMinutes) {
            $context->buildViolation(
                'L\'heure de fin de pause ({{ pause_fin }}) doit être avant ou égale à l\'heure de fin de l\'événement ({{ evenement_fin }}).'
            )
                ->setParameter('{{ pause_fin }}', $this->pauseFin->format('H:i'))
                ->setParameter('{{ evenement_fin }}', $evenement->getHeureFin()->format('H:i'))
                ->atPath('pauseFin')
                ->addViolation();
        }
    }
}
