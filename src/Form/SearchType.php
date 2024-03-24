<?php

namespace App\Form;

use App\Model\SearchData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;


class SearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('q', TextType::class, [
            'attr' => [
                'placeholder' => 'Recherche via un mot clé_'
            ]
        ]);
    }

    public function configuredOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'date_class' => SearchData::class,
            'method' =>'GET',
            'csrf_protection' => false
        ]);
    }
}