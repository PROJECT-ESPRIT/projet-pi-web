<?php

namespace App\Form;

use App\Entity\Forum;
use App\Entity\ForumReponse;
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
            ->add('forum', EntityType::class, [
                'class' => Forum::class,
                'choice_label' => static function (Forum $forum): string {
                    return sprintf('#%d - %s', $forum->getId(), $forum->getSujet());
                },
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
