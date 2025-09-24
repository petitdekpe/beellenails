<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Form;

use App\Entity\PromoCode;
use App\Entity\Prestation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints as Assert;

class PromoCodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code promotionnel *',
                'attr' => [
                    'class' => 'block w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-pink-500 focus:border-pink-500 uppercase',
                    'placeholder' => 'Ex: WELCOME10',
                    'maxlength' => 50
                ],
                'help' => 'Lettres majuscules, chiffres, tirets et underscores uniquement'
            ])
            ->add('generateCode', ButtonType::class, [
                'label' => '🎲 Générer',
                'attr' => [
                    'class' => 'ml-2 px-3 py-2 bg-gray-500 hover:bg-gray-600 text-white text-sm rounded-lg',
                    'onclick' => 'generateRandomCode()'
                ]
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom de la campagne *',
                'attr' => [
                    'class' => 'block w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-pink-500 focus:border-pink-500',
                    'placeholder' => 'Ex: Promotion de bienvenue'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'block w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-pink-500 focus:border-pink-500',
                    'rows' => 3,
                    'placeholder' => 'Description interne de cette promotion...'
                ]
            ])
            ->add('discountType', ChoiceType::class, [
                'label' => 'Type de réduction *',
                'choices' => [
                    'Pourcentage (%)' => 'percentage',
                    'Montant fixe (F CFA)' => 'fixed_amount'
                ],
                'attr' => [
                    'class' => 'block w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-pink-500 focus:border-pink-500',
                    'onchange' => 'updateDiscountUnit()'
                ]
            ])
            ->add('discountValue', NumberType::class, [
                'label' => 'Valeur de la réduction *',
                'scale' => 2,
                'attr' => [
                    'class' => 'block w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-pink-500 focus:border-pink-500',
                    'placeholder' => '10.00',
                    'min' => '0',
                    'step' => '0.01'
                ]
            ])
            ->add('minimumAmount', NumberType::class, [
                'label' => 'Montant minimum (F CFA)',
                'required' => false,
                'scale' => 2,
                'attr' => [
                    'class' => 'block w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-pink-500 focus:border-pink-500',
                    'placeholder' => '50.00',
                    'min' => '0',
                    'step' => '0.01'
                ],
                'help' => 'Montant minimum de commande pour utiliser ce code'
            ])
            ->add('maximumDiscount', NumberType::class, [
                'label' => 'Réduction maximale (F CFA)',
                'required' => false,
                'scale' => 2,
                'attr' => [
                    'class' => 'block w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-pink-500 focus:border-pink-500',
                    'placeholder' => '100.00',
                    'min' => '0',
                    'step' => '0.01'
                ],
                'help' => 'Plafond de réduction (utile pour les pourcentages)'
            ])
            ->add('validFrom', DateTimeType::class, [
                'label' => 'Valide à partir du *',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'block w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-pink-500 focus:border-pink-500'
                ]
            ])
            ->add('validUntil', DateTimeType::class, [
                'label' => 'Valide jusqu\'au *',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'block w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-pink-500 focus:border-pink-500'
                ]
            ])
            ->add('maxUsageGlobal', IntegerType::class, [
                'label' => 'Utilisation maximale globale',
                'required' => false,
                'attr' => [
                    'class' => 'block w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-pink-500 focus:border-pink-500',
                    'placeholder' => '100',
                    'min' => '1'
                ],
                'help' => 'Nombre total d\'utilisations autorisées (laisser vide = illimité)'
            ])
            ->add('maxUsagePerUser', IntegerType::class, [
                'label' => 'Utilisation maximale par utilisateur',
                'required' => false,
                'attr' => [
                    'class' => 'block w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-pink-500 focus:border-pink-500',
                    'placeholder' => '1',
                    'min' => '1'
                ],
                'help' => 'Nombre d\'utilisations par client (laisser vide = illimité)'
            ])
            ->add('eligiblePrestations', EntityType::class, [
                'class' => Prestation::class,
                'choice_label' => 'Title',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'Prestations éligibles',
                'attr' => [
                    'class' => 'block w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-pink-500 focus:border-pink-500',
                    'size' => 8
                ],
                'help' => 'Laisser vide pour toutes les prestations. Maintenir Ctrl pour sélectionner plusieurs.'
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Code actif',
                'required' => false,
                'attr' => [
                    'class' => 'rounded border-gray-300 text-pink-600 shadow-sm focus:border-pink-300 focus:ring focus:ring-pink-200 focus:ring-opacity-50'
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer le code promo',
                'attr' => [
                    'class' => 'w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-pink-600 hover:bg-pink-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pink-500'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PromoCode::class,
        ]);
    }
}