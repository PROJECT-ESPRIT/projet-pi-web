<?php

namespace App\Form;

use App\Entity\Produit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\File;

class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du produit',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom est obligatoire']),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 50,
                        'minMessage' => 'Le nom doit faire au moins {{ limit }} caractères',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['class' => 'form-control', 'rows' => 4],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La description est obligatoire']),
                    new Assert\Length([
                        'min' => 10,
                        'max' => 500,
                        'minMessage' => 'La description doit faire au moins {{ limit }} caractères',
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
            ])
            ->add('prix', MoneyType::class, [
                'label' => 'Prix',
                'currency' => 'TND',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le prix est obligatoire']),
                    new Assert\Positive(['message' => 'Le prix doit être positif']),
                ],
            ])
            ->add('stock', IntegerType::class, [
                'label' => 'Stock disponible',
                'attr' => ['class' => 'form-control', 'min' => 0],
                'constraints' => [
                    new Assert\NotNull(['message' => 'Le stock est obligatoire']),
                    new Assert\PositiveOrZero(['message' => 'Le stock ne peut pas être négatif']),
                ],
            ])
            ->add('image', FileType::class, [
                'label' => 'Image du produit',
                'mapped' => false, // si pas de propriété image dans l’entité
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, WEBP).',
                    ]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'btn btn-primary mt-3'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Produit::class,
        ]);
    }
}