<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class TermsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
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