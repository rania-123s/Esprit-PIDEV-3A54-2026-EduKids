<?php

namespace App\Form\Quiz;

use App\Entity\Quiz\Quiz;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuizType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre du quiz',
                'attr' => ['placeholder' => 'Ex: Mathématiques - Niveau 1'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 4, 'placeholder' => 'Décrivez le contenu du quiz...'],
            ])
            ->add('imageUrl', TextType::class, [
                'label' => 'Image (URL)',
                'required' => false,
                'help' => 'Laissez vide ou générez une image avec l\'IA.',
                'attr' => ['placeholder' => 'https://...'],
            ])
            ->add('published', CheckboxType::class, [
                'label' => 'Publier (visible pour les utilisateurs)',
                'required' => false,
            ])
            ->add('chatbotEnabled', CheckboxType::class, [
                'label' => 'Activer l\'assistant chatbot pendant le quiz',
                'required' => false,
                'help' => 'Si activé, les utilisateurs pourront poser des questions à l\'assistant pendant qu\'ils passent le quiz.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Quiz::class,
        ]);
    }
}
