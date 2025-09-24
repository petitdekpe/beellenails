<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Form;

use App\Entity\FormationResource;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Vich\UploaderBundle\Form\Type\VichFileType;

class FormationResourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de la ressource',
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5',
                    'placeholder' => 'Ex: Guide technique PDF'
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description de la ressource',
                'required' => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5',
                    'rows' => 2,
                    'placeholder' => 'Décrivez cette ressource...'
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de ressource',
                'choices' => [
                    'PDF' => 'pdf',
                    'Template' => 'template',
                    'Document' => 'document',
                    'Image' => 'image',
                ],
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            ])
            ->add('file', VichFileType::class, [
                'label' => 'Fichier à télécharger',
                'required' => false,
                'allow_delete' => true,
                'delete_label' => 'Supprimer le fichier actuel',
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            ])
            ->add('isDownloadable', CheckboxType::class, [
                'label' => 'Autoriser le téléchargement',
                'required' => false,
                'data' => true,
                'attr' => ['class' => 'w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FormationResource::class,
        ]);
    }
}