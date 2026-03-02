<?php

namespace App\Form;

use App\Entity\Forum;
use App\Entity\ForumReponse;
<<<<<<< HEAD
use App\Entity\User;
=======
>>>>>>> c4d1c44b0746a7387dc28bd3111400a167bda2d9
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ForumReponseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contenu')
<<<<<<< HEAD
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
=======
            ->add('forum', EntityType::class, [
                'class' => Forum::class,
                'choice_label' => static function (Forum $forum): string {
                    return sprintf('#%d - %s', $forum->getId(), $forum->getSujet());
                },
>>>>>>> c4d1c44b0746a7387dc28bd3111400a167bda2d9
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
