<?php

namespace App\Form;

use App\Entity\Creneau;
use App\Entity\Prestation;
use App\Entity\Rendezvous;
use App\Entity\CategoryPrestation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class RendezvousType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('day', DateType::class, [
                'label' => 'Date du rendez-vous',
                'placeholder' => 'Cliquez pour choisir une date de rendez-vous',
                'attr' => ['id'=>"datepicker2",'class' =>' bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5'],
                'widget' => 'single_text',
                'html5' => false,
                'format' => 'dd-mm-yyyy'

            // Vous pouvez également ajouter d'autres options de champ ici si nécessaire
        ])
        ->add('categorie', EntityType::class, [
            'label' => 'Choisissez une catégorie de prestation',
            'class' => CategoryPrestation::class,
            'choice_label' => 'NomCategory',
            'placeholder' => 'Sélectionnez une catégorie',
            'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500'],
            'mapped' => false, // Ceci signifie que le champ n'est pas lié à une propriété de l'entité Produit
        ])
        ->add('prestation', EntityType::class, [
            'label' => 'Choisissez une prestation',
            'class' => Prestation::class,
            'choice_label' => 'Title',
            'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500']
        ])
        ->add('creneau', EntityType::class, [
            'label' => 'Choisissez un créneau',
            'class' => Creneau::class,
            'choice_label' => 'libelle',
            'expanded' => true,
            'multiple' => false,
            'attr' => ['class' => '']
        ])
        ->add('image', VichImageType::class, [
            'label' => 'Une photo de vos mains / pieds',
            'required' => true,
            'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500'],
            'help' => 'Ajouter une photo de vos mains ou pieds au naturel afin que nous puissions les analyser.'
            
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rendezvous::class,
        ]);
    }
}
