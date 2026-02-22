<?php

namespace App\Form;

use App\Entity\Charity;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CharityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Nom de la cause'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'attr' => ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Décrivez votre cause'],
            ])
            ->add('pictureFile', FileType::class, [
                'label' => 'Upload your charity picture',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('picture', TextType::class, [
                'label' => 'Image path',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://... ou uploads/charities/xxx.png'],
            ])
            ->add('minimumAmount', NumberType::class, [
                'label' => 'Objectif de collecte (optionnel)',
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => ['class' => 'form-control', 'min' => 0, 'step' => '0.01'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Charity::class,
        ]);
    }
}
