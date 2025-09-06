<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Form;

use App\Entity\Creneau;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateType;


class ChoixDateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('day', DateType::class, [
                'label' => 'Date du rendez-vous',
                'placeholder' => 'Cliquez pour choisir une date de rendez-vous',
                'widget' => 'single_text',
                'html5' => false,
                'format' => 'dd-mm-yyyy',
                'required' => 'false',
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],

            // Vous pouvez également ajouter d'autres options de champ ici si nécessaire
        ])

        ->add('creneau', EntityType::class, [
            'label' => 'Choisissez un créneau',
            'class' => Creneau::class,
            'choice_label' => 'libelle',
            'expanded' => true,
            'multiple' => false,
            
        ]);

        
    }

    public function getBlockPrefix()
    {
        return 'step2_form';
    }
}
