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
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class RendezvousModifyType extends AbstractType
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
                'required' => true

            // Vous pouvez également ajouter d'autres options de champ ici si nécessaire
        ])
        
        ->add('creneau', EntityType::class, [
            'label' => 'Choisissez un créneau',
            'class' => Creneau::class,
            'choice_label' => 'libelle',
            'expanded' => true,
            'multiple' => false,
            'required'=> true,
            'attr' => ['class' => '']
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
