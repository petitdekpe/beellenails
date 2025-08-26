<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>

namespace App\Controller\Admin;

use App\Entity\Rendezvous;
use Vich\UploaderBundle\Form\Type\VichImageType;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class RendezvousCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Rendezvous::class;
    }

    
    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('User', 'Client:'),
            AssociationField::new('prestation', 'Prestation:'),
            DateField::new('day', 'Date:'),
            AssociationField::new('creneau', 'Créneau:'),
            AssociationField::new('supplement', 'Ajouter à la prestation:'),
            TextField::new('image', 'Photo client:')->setFormType(VichImageType::class),
            ImageField::new('imageName')->setBasePath('assets/images/rendezvous')->setUploadDir('public/assets/images/rendezvous')->onlyOnIndex(),
            TextField::new('status', 'Statut'),
        ];
    }
    
}
