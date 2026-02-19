<?php

namespace App\Controller\Admin;

use App\Entity\Lecon;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class LeconCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Lecon::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Lecon')
            ->setEntityLabelInPlural('Lecons')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            AssociationField::new('cours')
                ->setFormTypeOption('choice_label', 'titre'),
            TextField::new('titre'),
            IntegerField::new('ordre'),
            ChoiceField::new('media_type')
                ->setChoices([
                    'PDF + Video' => 'pdf_video',
                ]),
            TextField::new('media_url'),
            TextField::new('video_url'),
            TextField::new('youtube_url'),
        ];
    }
}

