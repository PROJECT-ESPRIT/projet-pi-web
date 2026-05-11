<?php

namespace App\Form;

use App\Entity\Donation;
use App\Entity\TypeDon;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DonationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EntityType::class, [
                'class' => TypeDon::class,
                'choice_label' => 'libelle',
                'label' => 'Type de don',
                'attr' => ['class' => 'form-control']
            ])
            ->add('amount', IntegerType::class, [
                'label' => 'Montant (DT)',
                'attr' => ['class' => 'form-control', 'min' => 0, 'placeholder' => 'Ex: 50'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description du don',
                'attr' => ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Décrivez votre don (ex: Une table en bois, 50 TND...)']
            ])
            ->add('isAnonymous', CheckboxType::class, [
                'label' => 'Don anonyme (seul l\'administrateur verra mon identité)',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Donation::class,
        ]);
    }
}
