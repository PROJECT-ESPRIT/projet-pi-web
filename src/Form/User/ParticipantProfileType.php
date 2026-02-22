<?php

namespace App\Form\User;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Url;

class ParticipantProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isBirthDateLocked = $options['birthdate_locked'];

        $builder
            ->add('prenom', TextType::class, [
                'label' => 'Prenom',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Youssef',
                ],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Jobrane',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
                'disabled' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Telephone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: +216 20 000 000',
                ],
                'constraints' => [
                    new Length(max: 20, maxMessage: 'Le numero de telephone ne doit pas depasser 20 caracteres.'),
                ],
            ])
            ->add('profileImageUrl', TextType::class, [
                'label' => 'Image de profil (URL)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://example.com/photo.jpg',
                ],
                'constraints' => [
                    new Url(message: 'Veuillez saisir une URL valide (ex: https://...).'),
                    new Length(max: 500, maxMessage: "L'URL de l'image ne doit pas depasser 500 caracteres."),
                ],
            ])
            ->add('dateNaissance', DateType::class, [
                'label' => 'Date de naissance',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'disabled' => $isBirthDateLocked,
                'help' => $isBirthDateLocked
                    ? 'Votre date de naissance est deja enregistree et ne peut pas etre modifiee.'
                    : 'Vous pouvez la renseigner une seule fois.',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new LessThanOrEqual('today', message: 'La date de naissance ne peut pas être dans le futur.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'birthdate_locked' => false,
        ]);

        $resolver->setAllowedTypes('birthdate_locked', 'bool');
    }
}
