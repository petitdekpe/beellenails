<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>


namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder

        ->add('Nom', null, [
            'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            'label' => 'Nom',
        ])
        ->add('Prenom', null, [
            'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            'label' => 'Prénom',
        ])
        ->add('email', null, [
            'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            'label' => 'Adresse mail',
            'constraints' => [
                new Email([
                    'message' => 'Veuillez saisir une adresse e-mail valide.',
                ]),
            ],
        ])
        ->add('Phone', null, [
            'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            'label' => 'Numéro de téléphone',
        ])
        ->add('agreeTerms', CheckboxType::class, [
            'mapped' => false,
            'constraints' => [
                new IsTrue([
                    'message' => 'Vous devez accepter nos conditions générales.',
                ]),
            ],
            'attr' => ['class' => 'w-4 h-4 border border-gray-300 rounded bg-gray-50 focus:ring-3 focus:ring-primary-300'],
            'label' => 'J\'accepte les conditions générales',
        ])
        ->add('plainPassword', PasswordType::class, [
            'mapped' => false,
            'attr' => [
                'autocomplete' => 'new-password',
                'class' => 'bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5',
            ],
            'constraints' => [
                new NotBlank([
                    'message' => 'Veuillez saisir un mot de passe',
                ]),
                new Length([
                    'min' => 6,
                    'minMessage' => 'Votre mot de passe doit comporter au moins {{ limit }} caractères',
                    'max' => 4096,
                ]),
            ],
            'label' => 'Password',
        ]);

               // Ajoutez un écouteur d'événements pour modifier les données avant la soumission
               $builder->addEventListener(
                FormEvents::PRE_SUBMIT,
                function (FormEvent $event) {
                    $data = $event->getData();
                    // Récupère le numéro de téléphone depuis les données soumises
                    $phoneNumber = $data['Phone'] ?? null;
                    
                    // Modifiez les données soumises pour utiliser le numéro de téléphone comme mot de passe
                    // C'est juste pour démonstration, ne le faites pas en production
                    $data['plainPassword'] = $phoneNumber;
    
                    // Met à jour les données du formulaire
                    $event->setData($data);
                }
            );
}




    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
