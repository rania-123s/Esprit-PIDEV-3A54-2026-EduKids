<?php

namespace App\Form;

use App\Entity\Cours;
use App\Entity\Lecon;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class LeconType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre de la leçon',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le titre de la leçon est obligatoire',
                    ]),
                ],
            ])
            ->add('ordre', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'constraints' => [
                    new NotBlank([
                        'message' => 'L\'ordre d\'affichage est obligatoire',
                    ]),
                    new Positive([
                        'message' => 'L\'ordre doit être un nombre positif',
                    ]),
                ],
            ])
            ->add('media_type', TextType::class, [
                'label' => 'Type de média',
                'help' => 'Exemple : video, pdf, image, text',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le type de média est obligatoire',
                    ]),
                ],
            ])
            ->add('media_url', TextType::class, [
                'label' => 'URL du média',
                'help' => 'Lien vers la vidéo, le PDF, l\'image ou le contenu',
                'constraints' => [
                    new NotBlank([
                        'message' => 'L\'URL du média est obligatoire',
                    ]),
                ],
            ])
            ->add('cours', EntityType::class, [
                'class' => Cours::class,
                'choice_label' => 'titre',
                'label' => 'Cours associé',
                'placeholder' => 'Sélectionnez un cours',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Vous devez sélectionner un cours',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lecon::class,
        ]);
    }
}
