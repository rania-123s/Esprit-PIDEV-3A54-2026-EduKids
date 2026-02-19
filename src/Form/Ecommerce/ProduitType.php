<?php

namespace App\Form\Ecommerce;

use App\Entity\Ecommerce\Produit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du produit',
                'attr' => ['placeholder' => 'Ex: Pack Mathématiques Débutant'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée',
                'required' => false,
                'attr' => ['rows' => 5, 'placeholder' => 'Décrivez le contenu du produit...'],
            ])
            ->add('prix', IntegerType::class, [
                'label' => 'Prix (en centimes)',
                'help' => 'Exemple : 1000 pour 10,00 €',
                'attr' => ['placeholder' => '0'],
            ])
            ->add('category', EntityType::class, [
                'class' => \App\Entity\Ecommerce\CategoryProduit::class,
                'choice_label' => 'nom',
                'label' => 'Catégorie',
                'placeholder' => 'Choisir une catégorie',
            ])
            ->add('imageUrl', TextType::class, [
                'label' => 'Image (URL)',
                'required' => false,
                'help' => 'Laissez vide pour utiliser une image par défaut.',
                'attr' => ['placeholder' => 'https://... ou /chemin/relatif'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Produit::class,
        ]);
    }
}
