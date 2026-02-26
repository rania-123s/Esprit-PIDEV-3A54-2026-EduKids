<?php

namespace App\Form\Quiz;

use App\Entity\Quiz\QuestionOption;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuestionOptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('texte', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Texte de l\'option',
                    'class' => 'form-control',
                ],
            ])
            ->add('ordre', IntegerType::class, [
                'label' => false,
                'attr' => ['min' => 0, 'class' => 'form-control form-control-sm', 'style' => 'width: 4rem;'],
            ])
            ->add('correct', CheckboxType::class, [
                'label' => 'Bonne réponse',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QuestionOption::class,
        ]);
    }
}
