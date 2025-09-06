<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FeexPayFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('phone', TextType::class, [
                'label' => 'Numéro de téléphone',
                'attr' => [
                    'placeholder' => '22901XXXXXXXX',
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-pink-600 focus:border-pink-600 block w-full p-2.5',
                    'inputmode' => 'numeric',
                    'id' => 'feexpay_phone',
                    'pattern' => '22901[0-9]{8}', // pour aide visuelle HTML
                    'title' => 'Format attendu : 22901XXXXXXXX',
                ],
            ])
            ->add('operator', ChoiceType::class, [
                'label' => 'Opérateur',
                'choices' => [
                    'MTN' => 'MTN',
                    'MOOV' => 'MOOV',
                    'CELTIIS' => 'CELTIIS BJ',
                ],
                'placeholder' => 'Sélectionnez un opérateur',
                'required' => true,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-pink-600 focus:border-pink-600 block w-full p-2.5',
                    'id' => 'feexpay_operator',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
        ]);
    }
}
