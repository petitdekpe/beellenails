<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Form;

use App\Entity\PaymentConfiguration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Type de configuration',
                'choices' => PaymentConfiguration::getAvailableTypes(),
                'disabled' => $options['edit_mode'] ?? false,
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('label', TextType::class, [
                'label' => 'Libellé',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Acompte pour rendez-vous'
                ]
            ])
            ->add('amount', MoneyType::class, [
                'label' => 'Montant',
                'currency' => 'XOF',
                'scale' => 0,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 5000'
                ]
            ])
            ->add('currency', ChoiceType::class, [
                'label' => 'Devise',
                'choices' => [
                    'Franc CFA (XOF)' => 'XOF',
                    'Euro (EUR)' => 'EUR',
                    'Dollar US (USD)' => 'USD',
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Description optionnelle de cette configuration'
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Configuration active',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PaymentConfiguration::class,
            'edit_mode' => false,
        ]);
    }
}