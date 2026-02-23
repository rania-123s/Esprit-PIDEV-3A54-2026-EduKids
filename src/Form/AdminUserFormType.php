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

class AdminUserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'Enter first name'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a first name.']),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'Enter last name'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a last name.']),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'Enter email address'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter an email.']),
                    new Email(['message' => 'Please enter a valid email address.']),
                ],
            ]);

        // Only add roles field if no fixed_role is set
        if (!$options['fixed_role']) {
            $builder->add('roles', ChoiceType::class, [
                'label' => 'Roles',
                'choices' => [
                    'Student (Eleve)' => 'ROLE_ELEVE',
                    'Parent' => 'ROLE_PARENT',
                    'Admin' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
                'expanded' => true,
            ]);
        }

        // Only add password field for new users (create mode)
        if ($options['is_create']) {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Password',
                    'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'Enter password'],
                ],
                'second_options' => [
                    'label' => 'Confirm Password',
                    'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'Confirm password'],
                ],
                'invalid_message' => 'The passwords do not match.',
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a password.']),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'Password must be at least {{ limit }} characters.',
                    ]),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_create' => false,
            'fixed_role' => null,
        ]);
    }
}
