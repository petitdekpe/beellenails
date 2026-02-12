<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller\Admin;

use App\Entity\Rendezvous;
use Vich\UploaderBundle\Form\Type\VichImageType;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;

class RendezvousCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Rendezvous::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setFormOptions(
                ['validation_groups' => ['admin']],
                ['validation_groups' => ['admin']]
            );
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('User', 'Client:'),
            AssociationField::new('prestation', 'Prestation:'),
            DateField::new('day', 'Date:'),
            AssociationField::new('creneau', 'Créneau:'),
            AssociationField::new('supplement', 'Ajouter à la prestation:'),
            TextField::new('image', 'Photo client (optionnel):')
                ->setFormType(VichImageType::class)
                ->setFormTypeOption('required', false)
                ->setHelp('Optionnel. Une image par défaut sera utilisée si non fournie.'),
            ImageField::new('imageName')->setBasePath('assets/images/rendezvous')->setUploadDir('public/assets/images/rendezvous')->onlyOnIndex(),
            TextField::new('status', 'Statut'),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        // Si aucune image n'est fournie, utiliser l'image par défaut
        if ($entityInstance instanceof Rendezvous) {
            if ($entityInstance->getImage() === null) {
                $defaultImagePath = $this->getParameter('kernel.project_dir') . '/public/assets/images/rendezvous/default.png';
                if (file_exists($defaultImagePath)) {
                    $entityInstance->setImage(new File($defaultImagePath));
                }
            }
        }

        parent::persistEntity($entityManager, $entityInstance);
    }
}
