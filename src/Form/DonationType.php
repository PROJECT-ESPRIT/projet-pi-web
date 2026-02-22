<?php

namespace App\Form;

use App\Entity\Charity;
use App\Entity\Donation;
use App\Entity\TypeDon;
use App\Entity\User;
use App\Repository\CharityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DonationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $donor = $options['donor'];

        $builder
            ->add('charity', EntityType::class, [
                'class' => Charity::class,
                'choice_label' => static function (Charity $charity): string {
                    $owner = $charity->getCreatedBy();
                    $ownerLabel = $owner ? trim(($owner->getPrenom() ?? '') . ' ' . ($owner->getNom() ?? '')) : 'N/A';
                    $objective = $charity->getMinimumAmount();
                    $objectiveLabel = $objective !== null ? number_format($objective, 2, '.', ' ') : 'Aucun';
                    $donationCount = $charity->getDonations()->count();

                    return sprintf(
                        '%s | Propriétaire: %s | Objectif: %s | Dons: %d',
                        (string) $charity->getTitle(),
                        $ownerLabel !== '' ? $ownerLabel : 'N/A',
                        $objectiveLabel,
                        $donationCount
                    );
                },
                'label' => 'Cause',
                'placeholder' => 'Choisissez une cause',
                'required' => true,
                'query_builder' => static function (CharityRepository $repository) use ($donor) {
                    $qb = $repository->createActiveQueryBuilder('c');
                    if ($donor instanceof User) {
                        $qb->andWhere('c.createdBy != :donor')
                            ->setParameter('donor', $donor);
                    }

                    return $qb;
                },
                'attr' => ['class' => 'form-control'],
            ])
            ->add('type', EntityType::class, [
                'class' => TypeDon::class,
                'choice_label' => 'libelle',
                'label' => 'Type de don',
                'attr' => ['class' => 'form-control']
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Montant (si don monétaire)',
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => ['class' => 'form-control', 'min' => 0, 'step' => '0.01'],
            ])
            ->add('photoFile', FileType::class, [
                'label' => 'Photo du don (optionnel)',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('isAnonymous', CheckboxType::class, [
                'label' => 'Don anonyme (masquer mon nom publiquement)',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description du don',
                'attr' => ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Décrivez votre don (ex: Une table en bois, 50 TND...)']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Donation::class,
            'donor' => null,
        ]);
        $resolver->setAllowedTypes('donor', ['null', User::class]);
    }
}
