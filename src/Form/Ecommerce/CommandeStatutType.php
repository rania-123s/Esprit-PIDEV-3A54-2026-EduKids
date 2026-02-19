<?php

namespace App\Form\Ecommerce;

use App\Entity\Ecommerce\Commande;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommandeStatutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => Commande::STATUT_EN_ATTENTE,
                    'Payé' => Commande::STATUT_PAYE,
                    'Livré' => Commande::STATUT_LIVRE,
                    'Annulé' => Commande::STATUT_ANNULE,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commande::class,
        ]);
    }
}
