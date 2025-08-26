<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>

namespace App\Controller\Admin;

use App\Entity\CategoryPrestation;
use Vich\UploaderBundle\Form\Type\VichImageType;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class CategoryPrestationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CategoryPrestation::class;
    }

    
    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('NomCategory', 'Nom de la Catégorie:'),
            TextField::new('image', 'Image:')->setFormType(VichImageType::class),
            ImageField::new('imageName')->setBasePath('assets/images/categoryprestations')->setUploadDir('public/assets/images/categoryprestations')->onlyOnIndex(),
        ];
    }
    
}
