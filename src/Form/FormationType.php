<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Form;

use App\Entity\Formation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use App\Form\FormationModuleType;
use App\Form\FormationResourceType;

class FormationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('Nom', TextType::class, [
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            ])
            ->add('Description', TextareaType::class, [
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            ])
            ->add('Prerequis', TextareaType::class, [
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            ])
            ->add('Objectif', TextareaType::class, [
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            ])
            ->add('Suivi', TextareaType::class, [
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            ])
            ->add('Programme', TextareaType::class, [
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            ])
            ->add('Cout', IntegerType::class, [
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            ])
            ->add('image', VichImageType::class, [
                'label' => 'Image de couverture',
                'required' => false,
                'allow_delete' => true,
                'delete_label' => 'Supprimer l\'image actuelle',
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            ])
            ->add('theme', ChoiceType::class, [
                'choices' => [
                    'Prothésie ongulaire' => 'prothesie_ongulaire',
                    'Nail Art' => 'nail_art',
                    'Soins des ongles' => 'soins_ongles',
                    'Techniques avancées' => 'techniques_avancees',
                    'Formation complète' => 'formation_complete',
                    'Perfectionnement' => 'perfectionnement',
                ],
                'placeholder' => 'Choisir une thématique',
                'required' => false,
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            ])
            ->add('level', ChoiceType::class, [
                'choices' => [
                    'Débutant' => 'debutant',
                    'Intermédiaire' => 'intermediaire',
                    'Avancé' => 'avance',
                    'Expert' => 'expert',
                ],
                'placeholder' => 'Choisir un niveau',
                'required' => false,
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            ])
            ->add('duration', IntegerType::class, [
                'label' => 'Durée totale (en minutes)',
                'required' => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5',
                    'placeholder' => 'Ex: 120 (pour 2h)'
                ],
            ])
            ->add('isFree', CheckboxType::class, [
                'label' => 'Formation gratuite',
                'required' => false,
                'attr' => ['class' => 'w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500'],
            ])
            ->add('accessType', ChoiceType::class, [
                'label' => 'Type d\'accès',
                'choices' => [
                    'Accès relatif (durée depuis inscription)' => 'relative',
                    'Accès fixe (entre deux dates)' => 'fixed',
                ],
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            ])
            ->add('accessDuration', IntegerType::class, [
                'label' => 'Durée d\'accès (en jours)',
                'required' => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5',
                    'placeholder' => 'Ex: 30 (pour 30 jours)'
                ],
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date de début (pour accès fixe)',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin (pour accès fixe)',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            ])
            ->add('instructorName', TextType::class, [
                'label' => 'Nom du formateur',
                'required' => false,
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            ])
            ->add('instructorBio', TextareaType::class, [
                'label' => 'Biographie du formateur',
                'required' => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5',
                    'rows' => 4
                ],
            ])
            ->add('instructorImage', VichImageType::class, [
                'label' => 'Photo du formateur',
                'required' => false,
                'allow_delete' => true,
                'delete_label' => 'Supprimer la photo actuelle',
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            ])
            ->add('youtubeUrl', UrlType::class, [
                'label' => 'Lien YouTube de présentation',
                'required' => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5',
                    'placeholder' => 'https://www.youtube.com/watch?v=...'
                ],
            ])
            ->add('targetAudience', TextareaType::class, [
                'label' => 'Public cible',
                'required' => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5',
                    'rows' => 3,
                    'placeholder' => 'Décrivez le public cible de cette formation...'
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Formation active',
                'required' => false,
                'data' => true,
                'attr' => ['class' => 'w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500'],
            ])
            ->add('modules', CollectionType::class, [
                'entry_type' => FormationModuleType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'label' => 'Modules de formation',
                'attr' => ['data-collection-holder' => 'modules'],
            ])
            ->add('resources', CollectionType::class, [
                'entry_type' => FormationResourceType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'label' => 'Ressources téléchargeables',
                'attr' => ['data-collection-holder' => 'resources'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Formation::class,
        ]);
    }
}
