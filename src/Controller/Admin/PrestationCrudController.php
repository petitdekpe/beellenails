<?php

namespace App\Controller\Admin;

use App\Entity\Prestation;
use Vich\UploaderBundle\Form\Type\VichImageType;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class PrestationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Prestation::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('categoryPrestation', 'Catégorie'),
            TextField::new('Title', 'Nom'),
            MoneyField::new('price', 'Prix')->setCurrency('XOF'),
            NumberField::new('duration', 'Durée'),
            TextField::new('image', 'Image:')->setFormType(VichImageType::class),
            ImageField::new('imageName')->setBasePath('assets/images/prestations')->setUploadDir('public/assets/images/prestations')->onlyOnIndex(),
            TextareaField::new('description'),
            TextareaField::new('inclus')

        ];
    }

    /*
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];
    }
    */
}
