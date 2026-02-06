<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoginFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('_username', EmailType::class, [
                'label' => 'Email address *',
                'attr' => [
                    'placeholder' => 'E-mail',
                    'class' => 'form-control border-0 bg-light rounded-end ps-1',
                    'autocomplete' => 'email',
                ],
                'required' => true,
            ])
            ->add('_password', PasswordType::class, [
                'label' => 'Password *',
                'attr' => [
                    'placeholder' => 'password',
                    'class' => 'form-control border-0 bg-light rounded-end ps-1',
                    'autocomplete' => 'current-password',
                ],
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_csrf_token',
        ]);
    }
}