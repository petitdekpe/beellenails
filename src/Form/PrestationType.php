<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>


namespace App\Form;

use App\Entity\Prestation;
use App\Entity\CategoryPrestation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class PrestationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('Title', TextType::class, [
                'label' => 'Nom de la prestation',
                ])
            ->add('price', TextType::class, [
                'label' => 'Prix (FCFA)',
                ])
            ->add('duration', TextType::class, [
                'label' => 'Durée (minute)',
                ])
            ->add('image', VichImageType::class, [
                'label' => 'Image', 
            ])
            ->add('image2', VichImageType::class, [
                'label' => 'Image 2',
                'required' => false,
            ])
            ->add('image3', VichImageType::class, [
                'label' => 'Image 3',
                'required' => false,
            ])
            ->add('CategoryPrestation', EntityType::class, [
                'label' => 'Choisissez une Categorie',
                'class' => CategoryPrestation::class,
                'choice_label' => 'NomCategory',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                ])
            ->add('inclus', TextareaType::class, [
                'label' => 'Inclus',
                ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Prestation::class,
        ]);
    }
}
