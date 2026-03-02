<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\Programme;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProgrammeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentProgramme = $options['data'] ?? null;
        $currentEvenementId = $currentProgramme && $currentProgramme->getEvenement()
            ? $currentProgramme->getEvenement()->getId()
            : null;

        $builder
            ->add('evenement', EntityType::class, [
                'class' => Evenement::class,
                'choice_label' => function (Evenement $evenement): string {
                    $dateLabel = $evenement->getDateEvenement()
                        ? $evenement->getDateEvenement()->format('d/m/Y')
                        : 'N/A';

                    $heuresLabel = 'horaires non definis';
                    if ($evenement->getHeureDebut() && $evenement->getHeureFin()) {
                        $heuresLabel = sprintf(
                            '%s - %s',
                            $evenement->getHeureDebut()->format('H:i'),
                            $evenement->getHeureFin()->format('H:i')
                        );
                    }

                    return sprintf('%s (%s, %s)', $evenement->getTitre(), $dateLabel, $heuresLabel);
                },
                'query_builder' => function (EntityRepository $er) use ($currentEvenementId) {
                    $qb = $er->createQueryBuilder('e')
                        ->orderBy('e.dateEvenement', 'ASC')
                        ->addOrderBy('e.heureDebut', 'ASC');

                    // In edit mode include current event, and include only valid events for all others.
                    if ($currentEvenementId) {
                        $qb->leftJoin('e.programme', 'p')
                            ->where('(p.id IS NULL AND e.heureDebut < e.heureFin) OR e.id = :currentId')
                            ->setParameter('currentId', $currentEvenementId);
                    } else {
                        // In create mode keep only events without program and with a valid time range.
                        $qb->leftJoin('e.programme', 'p')
                            ->where('p.id IS NULL')
                            ->andWhere('e.heureDebut < e.heureFin');
                    }

                    return $qb;
                },
                'placeholder' => 'Selectionnez un evenement',
                'label' => 'Evenement associe',
                'attr' => ['class' => 'form-select'],
                'required' => true,
            ])
            ->add('pauseDebut', TimeType::class, [
                'label' => 'Heure debut de la pause',
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
                'label' => 'Activites prevues',
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Decrivez les activites prevues pour cet evenement...
Exemple:
- 09h00 : Accueil des participants
- 09h30 : Atelier peinture
- 10h30 : Jeux educatifs
- 11h30 : Activite sportive',
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
- Autorisation parentale signee
- Certificat medical
- Fiche d inscription complete',
                    'class' => 'form-control',
                ],
                'required' => true,
            ])
            ->add('materielsRequis', TextareaType::class, [
                'label' => 'Materiels requis',
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Listez le materiel necessaire pour l evenement...
Exemple:
- Tenue de sport
- Gourde d eau
- Chapeau/casquette
- Creme solaire',
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
