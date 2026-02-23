<?php

namespace App\Form;

use App\Entity\Cours;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class CoursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre du cours',
                'attr'  => [
                    'placeholder' => 'Exemple : Mathématiques 6ème année',
                    'class'       => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a course title.']),
                    new Length([
                        'min' => 3,
                        'max' => 255,
                        'minMessage' => 'Title must be at least {{ limit }} characters.',
                        'maxMessage' => 'Title cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description du cours',
                'attr'  => [
                    'rows'        => 6,
                    'placeholder' => 'Décrivez le contenu, les objectifs, le public cible, les prérequis...',
                    'class'       => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a description.']),
                    new Length([
                        'min' => 10,
                        'minMessage' => 'Description must be at least {{ limit }} characters.',
                    ]),
                ],
            ])

            ->add('niveau', IntegerType::class, [
                'label' => 'Niveau (ex: 1 = CP, 7 = 1ère année collège, etc.)',
                'attr'  => [
                    'min'   => 1,
                    'max'   => 13,
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please select a level.']),
                    new Range([
                        'min' => 1,
                        'max' => 13,
                        'notInRangeMessage' => 'Level must be between {{ min }} and {{ max }}.',
                    ]),
                ],
            ])
            ->add('matiere', TextType::class, [
                'label' => 'Matière',
                'attr'  => [
                    'placeholder' => 'Exemple : Mathématiques, Français, SVT, Arabe...',
                    'class'       => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a subject.']),
                ],
            ])
            ->add('image', TextType::class, [
                'label' => 'Image (URL ou chemin relatif)',
                'attr'  => [
                    'placeholder' => 'Exemple : image',
                    'class'       => 'form-control',
                ],
                'required' => false,
            ]);
           
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Cours::class,
        ]);
    }
}