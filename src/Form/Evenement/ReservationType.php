<?php

namespace App\Form\Evenement;

use App\Entity\Reservation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre nom',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom est obligatoire.']),
                ],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre prénom',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le prénom est obligatoire.']),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'votre.email@example.com',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => "L'email est obligatoire."]),
                    new Assert\Email(['message' => "L'email n'est pas valide."]),
                ],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '06 12 34 56 78',
                ],
            ])
            ->add('nbAdultes', IntegerType::class, [
                'label' => "Nombre d'adultes",
                'data' => 0,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'value' => 0,
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => "Le nombre d'adultes est obligatoire."]),
                    new Assert\GreaterThanOrEqual(['value' => 0, 'message' => "Le nombre d'adultes doit être positif ou nul."]),
                ],
            ])
            ->add('nbEnfants', IntegerType::class, [
                'label' => "Nombre d'enfants",
                'data' => 0,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'value' => 0,
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => "Le nombre d'enfants est obligatoire."]),
                    new Assert\GreaterThanOrEqual(['value' => 0, 'message' => "Le nombre d'enfants doit être positif ou nul."]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}

