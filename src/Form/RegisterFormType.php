<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class RegisterFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First Name *',
                'required' => true,
                'attr' => ['placeholder' => 'First Name'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter your first name.',
                    ]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name *',
                'required' => true,
                'attr' => ['placeholder' => 'Last Name'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter your last name.',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email address *',
                'attr' => ['placeholder' => 'E-mail'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter your email address.',
                    ]),
                    new Email([
                        'message' => 'Please enter a valid email address.',
                    ]),
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'I am a *',
                'choices' => [
                    'Student (Eleve)' => 'ROLE_ELEVE',
                    'Parent' => 'ROLE_PARENT',
                ],
                'expanded' => true,
                'multiple' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-check'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select your role.',
                    ]),
                ],
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Password *',
                    'attr' => ['placeholder' => '*********'],
                ],
                'second_options' => [
                    'label' => 'Confirm Password *',
                    'attr' => ['placeholder' => '*********'],
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password.',
                    ]),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'Password must be at least 8 characters long.',
                    ]),
                ],
                'invalid_message' => 'The passwords do not match. Please try again.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}