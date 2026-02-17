<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'votre.email@example.com',
                ],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre nom',
                ],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prenom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre prenom',
                ],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Telephone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '06 12 34 56 78',
                ],
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Type de compte',
                'mapped' => false,
                'choices' => [
                    'Participant' => 'ROLE_PARTICIPANT',
                    'Artiste' => 'ROLE_ARTISTE',
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'class' => 'form-control',
                    ],
                ],
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => [
                        'placeholder' => 'Minimum 8 caracteres',
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => [
                        'placeholder' => 'Repetez le mot de passe',
                    ],
                ],
                'invalid_message' => 'Les mots de passe doivent correspondre.',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un mot de passe.',
                    ]),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'Votre mot de passe doit contenir au moins {{ limit }} caracteres.',
                        'max' => 4096,
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/',
                        'message' => 'Le mot de passe doit contenir une minuscule, une majuscule, un chiffre et un caractere special.',
                    ]),
                    new NotCompromisedPassword([
                        'message' => 'Ce mot de passe est trop faible. Choisissez-en un autre.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
