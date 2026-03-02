<?php

namespace App\Form;

use App\Entity\Evenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Déterminer si l'image est requise (nouvelle création ou pas d'image existante)
        $imageRequired = $options['is_new'] || empty($options['current_image']);
        
        $imageConstraints = [
            new File([
                'maxSize' => '5M',
                'mimeTypes' => [
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'image/webp',
                ],
                'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPEG, PNG, GIF ou WebP).',
                'maxSizeMessage' => 'L\'image est trop volumineuse. Taille maximale: {{ limit }}.',
            ])
        ];
        
        // Ajouter la contrainte NotBlank seulement si l'image est requise
        if ($imageRequired) {
            array_unshift($imageConstraints, new NotBlank([
                'message' => 'L\'image de l\'événement est obligatoire.',
            ]));
        }
        
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre de l\'événement',
                'attr' => [
                    'placeholder' => 'Exemple : Conférence annuelle 2026',
                    'class' => 'form-control',
                ],
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Décrivez l\'événement...',
                    'class' => 'form-control',
                ],
                'required' => true,
            ])
            ->add('dateEvenement', DateType::class, [
                'label' => 'Date de l\'événement',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
                'required' => true,
            ])
            ->add('imageUpload', FileType::class, [
                'label' => 'Image de l\'événement',
                'mapped' => false,
                'required' => $imageRequired,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*',
                ],
                'constraints' => $imageConstraints,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
            'is_new' => true,
            'current_image' => null,
        ]);
    }
}
