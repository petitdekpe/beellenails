<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Form;

use App\Entity\FormationModule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class FormationModuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du module',
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5',
                    'placeholder' => 'Ex: Introduction aux techniques de base'
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description du module',
                'required' => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5',
                    'rows' => 3,
                    'placeholder' => 'Décrivez le contenu de ce module...'
                ],
            ])
            ->add('duration', IntegerType::class, [
                'label' => 'Durée (en minutes)',
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5',
                    'placeholder' => 'Ex: 45',
                    'min' => 1
                ],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Position dans la formation',
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5',
                    'placeholder' => 'Ex: 1',
                    'min' => 1
                ],
            ])
            ->add('youtubeUrl', UrlType::class, [
                'label' => 'Lien YouTube du module',
                'required' => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5',
                    'placeholder' => 'https://www.youtube.com/watch?v=...'
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Module actif',
                'required' => false,
                'data' => true,
                'attr' => ['class' => 'w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FormationModule::class,
        ]);
    }
}