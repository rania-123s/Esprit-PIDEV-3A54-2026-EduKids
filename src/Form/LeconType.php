<?php

namespace App\Form;

use App\Entity\Cours;
use App\Entity\Lecon;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Url;

class LeconType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre de la lecon',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le titre de la lecon est obligatoire',
                    ]),
                ],
            ])
            ->add('ordre', IntegerType::class, [
                'label' => "Ordre d'affichage",
                'constraints' => [
                    new NotBlank([
                        'message' => "L'ordre d'affichage est obligatoire",
                    ]),
                    new Positive([
                        'message' => "L'ordre doit etre un nombre positif",
                    ]),
                ],
            ])
            ->add('media_type', ChoiceType::class, [
                'label' => 'Type de media',
                'choices' => [
                    'PDF + Video' => 'pdf_video',
                ],
                'data' => 'pdf_video',
                'help' => 'Type fixe: une lecon contient un PDF et une video.',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le type de media est obligatoire',
                    ]),
                    new Choice([
                        'choices' => ['pdf_video'],
                        'message' => 'Le type de media doit etre PDF + video',
                    ]),
                ],
            ])
            ->add('media_url', UrlType::class, [
                'label' => 'URL du media',
                'required' => false,
                'help' => "Lien HTTP/HTTPS vers la video, le PDF, l'image ou le contenu",
                'constraints' => [
                    new Url([
                        'message' => 'Entrez une URL valide (exemple : https://exemple.com/video.mp4)',
                        'protocols' => ['http', 'https'],
                    ]),
                ],
            ])
            ->add('pdf_file', FileType::class, [
                'label' => 'Fichier PDF',
                'mapped' => false,
                'required' => false,
                'help' => 'Choisissez un fichier PDF.',
                'constraints' => [
                    new File([
                        'maxSize' => '200M',
                        'maxSizeMessage' => 'Le fichier ne doit pas depasser 200 Mo',
                        'mimeTypes' => [
                            'application/pdf',
                        ],
                        'mimeTypesMessage' => 'Format autorise : pdf',
                    ]),
                ],
            ])
            ->add('video_file', FileType::class, [
                'label' => 'Fichier video',
                'mapped' => false,
                'required' => false,
                'help' => 'Choisissez un fichier video (MP4, WEBM ou OGG).',
                'constraints' => [
                    new File([
                        'maxSize' => '200M',
                        'maxSizeMessage' => 'Le fichier ne doit pas depasser 200 Mo',
                        'mimeTypes' => [
                            'video/mp4',
                            'video/webm',
                            'video/ogg',
                        ],
                        'mimeTypesMessage' => 'Formats autorises : mp4, webm, ogg',
                    ]),
                ],
            ])
            ->add('youtube_url', UrlType::class, [
                'label' => 'Lien YouTube',
                'mapped' => false,
                'required' => false,
                'help' => 'Collez un lien YouTube (youtube.com ou youtu.be).',
                'constraints' => [
                    new Url([
                        'message' => 'Entrez une URL valide',
                        'protocols' => ['http', 'https'],
                    ]),
                    new Regex([
                        'pattern' => '/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\//i',
                        'message' => 'Le lien doit provenir de YouTube',
                    ]),
                ],
            ])
            ->add('cours', EntityType::class, [
                'class' => Cours::class,
                'choice_label' => 'titre',
                'label' => 'Cours associe',
                'placeholder' => 'Selectionnez un cours',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Vous devez selectionner un cours',
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
