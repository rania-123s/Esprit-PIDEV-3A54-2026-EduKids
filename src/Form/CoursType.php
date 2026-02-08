<?php

namespace App\Form;

use App\Entity\Cours;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

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
                    'maxlength'   => 255,
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le titre du cours est obligatoire',
                    ]),
                    new Length([
                        'min' => 3,
                        'max' => 255,
                        'minMessage' => 'Le titre doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le titre ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description du cours',
                'attr'  => [
                    'rows'        => 6,
                    'placeholder' => 'Décrivez le contenu, les objectifs, le public cible, les prérequis...',
                    'class'       => 'form-control',
                    'maxlength'   => 1000,
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'La description du cours est obligatoire',
                    ]),
                    new Length([
                        'min' => 10,
                        'max' => 1000,
                        'minMessage' => 'La description doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
            ])

            ->add('niveau', IntegerType::class, [
                'label' => 'Niveau (1 = CP, 6 = 6ème, 7 = 5ème, ..., 13 = Terminale)',
                'attr'  => [
                    'min'   => 1,
                    'max'   => 13,
                    'class' => 'form-control',
                    'placeholder' => 'Choisissez un niveau entre 1 et 13',
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le niveau est obligatoire',
                    ]),
                    new Range([
                        'min' => 1,
                        'max' => 13,
                        'notInRangeMessage' => 'Le niveau doit être compris entre {{ min }} et {{ max }}',
                    ]),
                ],
            ])

            ->add('matiere', TextType::class, [
                'label' => 'Matière',
                'attr'  => [
                    'placeholder' => 'Exemple : Mathématiques, Français, SVT, Arabe...',
                    'class'       => 'form-control',
                    'maxlength'   => 100,
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'La matière est obligatoire',
                    ]),
                    new Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'La matière doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'La matière ne peut pas dépasser {{ limit }} caractères',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-ZÀ-ÿ\s\'-]+$/u',
                        'message' => 'La matière ne peut contenir que des lettres, espaces, apostrophes et tirets',
                    ]),
                ],
            ])

            ->add('image', FileType::class, [
                'label' => 'Image du cours',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/jpeg,image/jpg,image/png,image/gif,image/webp',
                ],
                'help' => 'Formats acceptés : JPG, PNG, GIF, WEBP. Taille maximale : 2 Mo',
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/jpg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPG, PNG, GIF ou WEBP)',
                        'maxSizeMessage' => 'Le fichier est trop volumineux ({{ size }} {{ suffix }}). Taille maximale autorisée : {{ limit }} {{ suffix }}',
                    ])
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Cours::class,
        ]);
    }
}