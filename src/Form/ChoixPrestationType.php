<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Form;

use App\Entity\Prestation;
use App\Entity\CategoryPrestation;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class ChoixPrestationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('categorie', EntityType::class, [
            'label' => 'Choisissez une catégorie de prestation',
            'class' => CategoryPrestation::class,
            'choice_label' => 'NomCategory',
            'placeholder' => 'Sélectionnez une catégorie',
            'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            'mapped' => false, // Ceci signifie que le champ n'est pas lié à une propriété de l'entité Rendez-vous
        ])
        ->add('prestation', ChoiceType::class, [
            'label' => 'Choisissez une prestation',
            'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            'placeholder' => 'Prestation (Choisir une catégorie)',
            'required'=> false,
            'expanded' => true,
            'multiple' => false,
        ]);

        $formModifier = function(FormInterface $form, CategoryPrestation $categoryPrestation = null) {
            $prestation = (null === $categoryPrestation) ? [] : $categoryPrestation->getPrestation();

            $form->add('prestation', EntityType::class, [
                'class' => Prestation::class,
                'choices'=> $prestation,
                'required'=> false,
                'choice_label' => 'Title',
                'placeholder' => 'Prestation (Choisir une catégorie)',
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
                'label' => 'Choisissez une prestation'
            ]);
        }; 

        $builder->get('categorie')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event) use ($formModifier){
                $categoryPrestations = $event->getForm()->getData();
                $formModifier($event->getForm()->getParent(), $categoryPrestations);
            }
        );
    }

    public function getBlockPrefix()
    {
        return 'step1_form';
    }
}
