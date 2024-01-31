<?php

namespace App\Form;

use App\Entity\Creneau;
use App\Entity\Prestation;
use App\Entity\Rendezvous;
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

class RendezvousType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('day', DateType::class, [
                'label' => 'Date du rendez-vous',
                'placeholder' => 'Cliquez pour choisir une date de rendez-vous',
                'widget' => 'single_text',
                'html5' => false,
                'format' => 'yyyy-MM-dd',
                'required' => 'false'

            // Vous pouvez également ajouter d'autres options de champ ici si nécessaire
        ])
        ->add('categorie', EntityType::class, [
            'label' => 'Choisissez une catégorie de prestation',
            'class' => CategoryPrestation::class,
            'choice_label' => 'NomCategory',
            'placeholder' => 'Sélectionnez une catégorie',
            'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500'],
            'mapped' => false, // Ceci signifie que le champ n'est pas lié à une propriété de l'entité Rendez-vous
        ])
        ->add('prestation', EntityType::class, [
            'class' => Prestation::class,
            'required'=> false,
            'choice_label' => 'Title',
            'placeholder' => 'Prestation (Choisir une catégorie)',
            'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500'],
            'label' => 'Choisissez une prestation'
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

        //$formModifier = function(FormInterface $form, CategoryPrestation $categoryPrestation = null) {
        //    $prestation = (null === $categoryPrestation) ? [] : $categoryPrestation->getPrestation();

        //    $form->add('prestation', EntityType::class, [
        //        'class' => Prestation::class,
        //        'choices'=> $prestation,
        //        'required'=> false,
        //        'choice_label' => 'Title',
        //        'placeholder' => 'Prestation (Choisir une catégorie)',
        //        'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500'],
        //        'label' => 'Choisissez une prestation'
        //    ]);
        //}; 

        //$builder->get('categorie')->addEventListener(
        //    FormEvents::POST_SUBMIT,
        //    function(FormEvent $event) use ($formModifier){
        //        $categoryPrestations = $event->getForm()->getData();
        //        $formModifier($event->getForm()->getParent(), $categoryPrestations);
        //    }
        //);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rendezvous::class,
        ]);
    }
}
