<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\Ressource;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class RessourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('evenement', EntityType::class, [
                'class' => Evenement::class,
                'choice_label' => 'titre',
                'label' => 'Événement',
                'attr' => [
                    'class' => 'form-control select2',
                ],
                'placeholder' => 'Sélectionnez un événement',
                'required' => true,
            ])
            ->add('typeRessource', ChoiceType::class, [
                'label' => 'Type de ressource',
                'choices' => [
                    'Activité' => 'activite',
                    'Pause' => 'pause',
                    'Document' => 'document',
                    'Matériel' => 'materiel',
                ],
                'attr' => [
                    'class' => 'form-control select2',
                ],
                'placeholder' => 'Sélectionnez un type',
                'required' => true,
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom de la ressource',
                'attr' => [
                    'placeholder' => 'Ex: Atelier créatif, Pause café...',
                    'class' => 'form-control',
                ],
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Décrivez cette ressource en détail...',
                    'class' => 'form-control',
                ],
                'required' => false,
            ])
            ->add('dateDebut', DateTimeType::class, [
                'label' => 'Date et heure de début',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
                'required' => false,
            ])
            ->add('dateFin', DateTimeType::class, [
                'label' => 'Date et heure de fin',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
                'required' => false,
            ])
            ->add('fichierUpload', FileType::class, [
                'label' => 'Fichier (PDF, Image, Document)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-powerpoint',
                            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger un fichier valide (PDF, Word, Excel, PowerPoint ou Image).',
                        'maxSizeMessage' => 'Le fichier est trop volumineux. Taille maximale: {{ limit }}.',
                    ])
                ],
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantité',
                'attr' => [
                    'min' => 0,
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 50',
                ],
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ressource::class,
        ]);
    }
}
