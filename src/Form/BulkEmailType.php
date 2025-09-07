<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Form;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints as Assert;

class BulkEmailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', TextType::class, [
                'label' => 'Objet de l\'email',
                'attr' => [
                    'class' => 'block w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-pink-500 focus:border-pink-500',
                    'placeholder' => 'Saisissez l\'objet de votre email...'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'L\'objet ne peut pas être vide']),
                    new Assert\Length(['max' => 255])
                ]
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr' => [
                    'class' => 'block w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-pink-500 focus:border-pink-500',
                    'rows' => 8,
                    'placeholder' => 'Rédigez votre message ici...'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le message ne peut pas être vide'])
                ]
            ])
            ->add('sendToAll', CheckboxType::class, [
                'label' => 'Envoyer à tous les clients',
                'required' => false,
                'attr' => [
                    'class' => 'rounded border-gray-300 text-pink-600 shadow-sm focus:border-pink-300 focus:ring focus:ring-pink-200 focus:ring-opacity-50'
                ]
            ])
            ->add('recipients', EntityType::class, [
                'class' => User::class,
                'choice_label' => function(User $user) {
                    return $user->getFullName() . ' (' . $user->getEmail() . ')';
                },
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'Ou sélectionner des clients spécifiques',
                'attr' => [
                    'class' => 'block w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-pink-500 focus:border-pink-500',
                    'size' => 8
                ],
                'query_builder' => function (UserRepository $repository) {
                    return $repository->createQueryBuilder('u')
                        ->where('u.roles NOT LIKE :admin_role')
                        ->setParameter('admin_role', '%ROLE_ADMIN%')
                        ->orderBy('u.Prenom', 'ASC')
                        ->addOrderBy('u.Nom', 'ASC');
                },
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Envoyer les emails',
                'attr' => [
                    'class' => 'w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-pink-600 hover:bg-pink-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pink-500'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}