<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PeriodCongeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('start_date', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de début',
                'html5' => false,
                'attr' => ['class' => 'datepicker', 'id' => 'test'],
            ])
            ->add('end_date', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de fin',
                'html5' => false,
                'attr' => ['class' => 'datepicker'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // Configurez vos options ici
        ]);
    }
}
