<?php

namespace App\Form;

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
use Symfony\Component\Validator\Constraints\NotBlank;
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
                'required' => true,
                'widget' => 'single_text',
                'html5' => false,
                'format' => 'yyyy-MM-dd',
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5', 'id' => 'datepicker'],
        ])
        ->add('categorie', EntityType::class, [
            'label' => 'Choisissez une catégorie de prestation',
            'class' => CategoryPrestation::class,
            'choice_label' => 'NomCategory',
            'required' => true,
            'placeholder' => 'Sélectionnez une catégorie',
            'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            'mapped' => false,
            'constraints' => [
                new NotBlank([
                    'message' => 'Veuillez sélectionner une catégorie',
                ]),
            ],
        ])
        ->add('prestation', EntityType::class, [
            'class' => Prestation::class,
            'required'=> true,
            'choice_label' => 'Title',
            'placeholder' => 'Choisir une prestation',
            'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            'label' => 'Choisissez une prestation'
        ])
        ->add('creneau', EntityType::class, [
            'label' => 'Choisissez un créneau',
            'class' => Creneau::class,
            'choice_label' => 'libelle',
            'required'=> true,
            'attr' => ['class' => 'hidden bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
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
        ])
        ->add('image', VichImageType::class, [
            'label' => 'Une photo de vos mains / pieds',
            'required' => true,
            'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
            'help' => 'Ajouter une photo de vos ongles des mains ou des pieds, naturels sans capsules et sans vernis afin que nous puissions les analyser. Dans le cas où vous souhaiteriez venir avec une pose non faites chez nous pour une dépose avant votre prestation, un supplément de 10.000f sera facturé pour la dépose complète.'
            
        ]);

        $formModifier = function(FormInterface $form, CategoryPrestation $categoryPrestation = null) {
            $prestation = (null === $categoryPrestation) ? [] : $categoryPrestation->getPrestation();

            $form->add('prestation', EntityType::class, [
                'class' => Prestation::class,
                'choices'=> $prestation,
                'required'=> true,
                'choice_label' => 'Title',
                'placeholder' => 'Prestation (Choisir une catégorie)',
                'attr' => ['class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5'],
                'label' => 'Choisissez une prestation',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner une prestation',
                    ]),
                ],
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rendezvous::class,
            'creneau_repository' => null,
        ]);
    }
}
