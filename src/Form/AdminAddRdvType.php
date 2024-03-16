<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\Creneau;
use App\Entity\Prestation;
use App\Entity\Rendezvous;
use App\Entity\Supplement;
use App\Entity\CategoryPrestation;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class AdminAddRdvType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('user', EntityType::class, [
            'class' => User::class,
            'required'=> true,
            'placeholder' => 'Choisir un(e) client(e)',
            'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            'label' => 'Choisissez un(e) client(e)'
        ])
        ->add('day', DateType::class, [
                'label' => 'Date du rendez-vous',
                'placeholder' => 'Cliquez pour choisir une date de rendez-vous',
                'widget' => 'single_text',
                'html5' => false,
                'format' => 'yyyy-MM-dd',
                'required' => true
        ])
        ->add('creneau', EntityType::class, [
            'label' => 'Choisissez un créneau',
            'class' => Creneau::class,
            'choice_label' => 'libelle',
            'required'=> true,
            'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
        ])
        ->add('prestation', EntityType::class, [
            'class' => Prestation::class,
            'required'=> true,
            'choice_label' => 'Title',
            'placeholder' => 'Choisir une prestation',
            'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            'label' => 'Choisissez une prestation'
        ])
        ->add('supplement', EntityType::class, [
            'class' => Supplement::class,
            'choice_label' => 'title',
            'expanded' => true,
            'multiple' => true,
            'attr' => [
                'class' => 'flex items-center mb-4' // Ajoutez vos classes CSS ici
            ],
            'choice_attr' => function($choice, $key, $value) {
                // Ajoutez les attributs supplémentaires pour chaque option si nécessaire
                return ['class' => 'w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500'];
            }
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rendezvous::class,
            'creneau_repository' => null,
        ]);
    }
}
