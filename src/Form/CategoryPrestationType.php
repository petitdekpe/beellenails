<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>


namespace App\Form;

use App\Entity\CategoryPrestation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryPrestationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('NomCategory')
            ->add('image', VichImageType::class, [
                'label' => 'Image',
                'required' => true, 
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CategoryPrestation::class,
        ]);
    }
}
