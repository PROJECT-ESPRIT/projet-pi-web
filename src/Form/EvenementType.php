<?php

namespace App\Form;

use App\Entity\Evenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre de l\'événement',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Atelier Poterie']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['class' => 'form-control', 'rows' => 5]
            ])
            ->add('dateDebut', DateTimeType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
                'required' => true
            ])
            ->add('dateFin', DateTimeType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
                'required' => true
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Salle des fêtes, Tunis']
            ])
            ->add('nbPlaces', IntegerType::class, [
                'label' => 'Nombre de places',
                'attr' => ['class' => 'form-control', 'min' => 1]
            ])
            ->add('ageMin', IntegerType::class, [
                'label' => 'Age Minimum',
                'required' => false,
                'attr' => ['class' => 'form-control', 'min' => 0]
            ])
            ->add('ageMax', IntegerType::class, [
                'label' => 'Age Maximum',
                'required' => false,
                'attr' => ['class' => 'form-control', 'min' => 0]
            ])
            ->add('prix', MoneyType::class, [
                'label' => 'Prix (laisser vide si gratuit)',
                'required' => false,
                'currency' => 'TND',
                'attr' => ['class' => 'form-control']
            ])
            // Image upload will be handled later if needed
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
        ]);
    }
}
