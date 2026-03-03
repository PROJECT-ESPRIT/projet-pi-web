<?php
// src/Form/PredictionType.php
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Entity\Produit;

class PredictionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('produit', EntityType::class, [
                'class' => Produit::class,
                'choice_label' => 'nom',
                'label' => 'Produit'
            ])
            ->add('mois', ChoiceType::class, [
                'choices' => [
                    'Janvier' => 1, 'Février' => 2, 'Mars' => 3,
                    'Avril' => 4, 'Mai' => 5, 'Juin' => 6,
                    'Juillet' => 7, 'Août' => 8, 'Septembre' => 9,
                    'Octobre' => 10, 'Novembre' => 11, 'Décembre' => 12,
                ],
                'label' => 'Mois'
            ])
            ->add('predict', SubmitType::class, ['label' => 'Prédire les ventes']);
    }
}