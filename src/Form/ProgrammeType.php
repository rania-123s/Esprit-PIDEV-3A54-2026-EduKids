<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\Programme;
use App\Repository\ProgrammeRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProgrammeType extends AbstractType
{
    private ProgrammeRepository $programmeRepository;

    public function __construct(ProgrammeRepository $programmeRepository)
    {
        $this->programmeRepository = $programmeRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentProgramme = $options['data'] ?? null;
        $currentEvenementId = $currentProgramme && $currentProgramme->getEvenement() 
            ? $currentProgramme->getEvenement()->getId() 
            : null;

        $builder
            ->add('evenement', EntityType::class, [
                'class' => Evenement::class,
                'choice_label' => function (Evenement $evenement) {
                    return $evenement->getTitre() . ' (' . ($evenement->getDateEvenement() ? $evenement->getDateEvenement()->format('d/m/Y') : 'N/A') . ')';
                },
                'query_builder' => function (EntityRepository $er) use ($currentEvenementId) {
                    $qb = $er->createQueryBuilder('e')
                        ->orderBy('e.dateEvenement', 'ASC');
                    
                    // En mode édition, inclure l'événement actuel + ceux sans programme
                    if ($currentEvenementId) {
                        $qb->leftJoin('e.programme', 'p')
                           ->where('p.id IS NULL OR e.id = :currentId')
                           ->setParameter('currentId', $currentEvenementId);
                    } else {
                        // En mode création, n'afficher que les événements sans programme
                        $qb->leftJoin('e.programme', 'p')
                           ->where('p.id IS NULL');
                    }
                    
                    return $qb;
                },
                'placeholder' => 'Sélectionnez un événement',
                'label' => 'Événement associé',
                'attr' => ['class' => 'form-select'],
                'required' => true,
            ])
            ->add('pauseDebut', TimeType::class, [
                'label' => 'Heure début de la pause',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'required' => true,
            ])
            ->add('pauseFin', TimeType::class, [
                'label' => 'Heure fin de la pause',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'required' => true,
            ])
            ->add('activites', TextareaType::class, [
                'label' => 'Activités prévues',
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Décrivez les activités prévues pour cet événement...
Exemple:
- 09h00 : Accueil des participants
- 09h30 : Atelier peinture
- 10h30 : Jeux éducatifs
- 11h30 : Activité sportive',
                    'class' => 'form-control',
                ],
                'required' => true,
            ])
            ->add('documentsRequis', TextareaType::class, [
                'label' => 'Documents requis',
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Listez les documents obligatoires pour participer...
Exemple:
- Autorisation parentale signée
- Certificat médical
- Fiche d\'inscription complète',
                    'class' => 'form-control',
                ],
                'required' => true,
            ])
            ->add('materielsRequis', TextareaType::class, [
                'label' => 'Matériels requis',
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Listez le matériel nécessaire pour l\'événement...
Exemple:
- Tenue de sport
- Gourde d\'eau
- Chapeau/casquette
- Crème solaire',
                    'class' => 'form-control',
                ],
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Programme::class,
        ]);
    }
}
