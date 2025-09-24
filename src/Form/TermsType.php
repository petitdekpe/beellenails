<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class TermsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('promoCode', TextType::class, [
            'mapped' => false,
            'required' => false,
            'attr' => [
                'class' => 'w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-pink-500 focus:border-pink-500',
                'placeholder' => 'Code promo (optionnel)'
            ],
            'label' => 'Code promo',
            'help' => 'Entrez un code promo valide pour bénéficier d\'une réduction'
        ])
        ->add('agreeTerms', CheckboxType::class, [
            'mapped' => false,
            'constraints' => [
                new IsTrue([
                    'message' => 'Vous devez accepter nos conditions générales.',
                ]),
            ],
            'attr' => ['class' => 'w-4 h-4 border border-pink-300 rounded bg-pink-500 focus:ring-3 focus:ring-primary-300'],
            'label' => 'J\'ai lu et j\'accepte les règles de fonctionnement et la politique d\'annulation.',
        ])
        ;
    }

    public function getBlockPrefix()
    {
        return 'recap_form';
    }
}