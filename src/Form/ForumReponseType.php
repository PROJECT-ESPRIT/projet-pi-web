<?php

namespace App\Form;

use App\Entity\Forum;
use App\Entity\ForumReponse;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ForumReponseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contenu')
            ->add('voiceMessage', FileType::class, [
                'label' => 'Message vocal (optionnel)',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'accept' => 'audio/*',
                    'class' => 'form-control'
                ]
            ])
            ->add('dateReponse', null, [
                'widget' => 'single_text',
            ])
            ->add('forum', EntityType::class, [
                'class' => Forum::class,
                'choice_label' => 'id',
            ])
            ->add('auteur', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ForumReponse::class,
        ]);
    }
}
