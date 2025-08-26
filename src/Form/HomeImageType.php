<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>

namespace App\Form;

use App\Entity\HomeImage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

class HomeImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de l\'image',
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-pink-600 focus:border-pink-600 block w-full p-2.5',
                    'placeholder' => 'Ex: Slide 1, Photo Muriel...'
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type d\'image',
                'choices' => HomeImage::getTypeChoices(),
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-pink-600 focus:border-pink-600 block w-full p-2.5'
                ]
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Position (ordre d\'affichage)',
                'required' => false,
                'help' => 'Plus le nombre est petit, plus l\'image apparaît en premier',
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-pink-600 focus:border-pink-600 block w-full p-2.5',
                    'min' => 0,
                    'max' => 100
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Image active (affichée sur le site)',
                'required' => false,
                'attr' => [
                    'class' => 'w-4 h-4 text-pink-600 bg-gray-100 border-gray-300 rounded focus:ring-pink-500 focus:ring-2'
                ]
            ])
            ->add('image', VichImageType::class, [
                'label' => 'Image',
                'required' => false,
                'allow_delete' => true,
                'delete_label' => 'Supprimer l\'image actuelle',
                'download_label' => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-pink-600 focus:border-pink-600 block w-full p-2.5'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (optionnel)',
                'required' => false,
                'help' => 'Description ou texte alternatif pour l\'image',
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-pink-600 focus:border-pink-600 block w-full p-2.5',
                    'rows' => 3
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HomeImage::class,
        ]);
    }
}