<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => $options['require_password'],
                'label' => $options['require_password']
                    ? 'Mot de passe'
                    : 'Mot de passe (laisser vide pour ne pas changer)',
                'attr' => ['class' => 'form-control', 'autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le mot de passe est obligatoire.',
                        'groups' => $options['require_password'] ? ['Default'] : ['never_validate'],
                    ]),
                    new Regex([
                        'pattern' => '/^$|^(?=.{8,}$)(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/',
                        'message' => 'Le mot de passe doit contenir au moins 8 caracteres, avec minuscule, majuscule, chiffre et caractere special.',
                    ]),
                ],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prenom',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Telephone',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Roles',
                'choices' => [
                    'Administrateur' => 'ROLE_ADMIN',
                    'Artiste' => 'ROLE_ARTISTE',
                    'Participant' => 'ROLE_PARTICIPANT',
                ],
                'multiple' => true,
                'expanded' => true,
                'attr' => ['class' => 'form-check'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'require_password' => false,
        ]);

        $resolver->setAllowedTypes('require_password', 'bool');
    }
}
