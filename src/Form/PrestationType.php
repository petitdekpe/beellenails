<?php

namespace App\Form;

use App\Entity\Prestation;
use App\Entity\CategoryPrestation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PrestationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('Title')
            ->add('price')
            ->add('duration')
            ->add('image', VichImageType::class, [
                'label' => 'Image',
                'required' => true, 
            ])
            ->add('CategoryPrestation', EntityType::class, [
                'label' => 'Choisissez une Category',
                'class' => CategoryPrestation::class,
                'choice_label' => 'NomCategory',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Prestation::class,
        ]);
    }
}
