<?php

namespace App\Controller\User;

use App\Form\User\ParticipantProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProfileController extends AbstractController
{
    #[Route('/participant/profile', name: 'participant_profile')]
    #[IsGranted('ROLE_PARTICIPANT')]
    public function participant(): Response
    {
        return $this->render('profile/participant.html.twig');
    }

    #[Route('/participant/profile/edit', name: 'participant_profile_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PARTICIPANT')]
    public function participantEdit(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $originalBirthDate = $user->getDateNaissance();
        $birthDateLocked = $originalBirthDate !== null;

        $form = $this->createForm(ParticipantProfileType::class, $user, [
            'birthdate_locked' => $birthDateLocked,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($birthDateLocked && $user->getDateNaissance() != $originalBirthDate) {
                $user->setDateNaissance($originalBirthDate);
                $this->addFlash('danger', 'La date de naissance ne peut etre modifiee qu une seule fois.');
            }

            $entityManager->flush();
            $this->addFlash('success', 'Votre profil a été mis à jour.');

            return $this->redirectToRoute('participant_profile');
        }

        return $this->render('profile/participant_edit.html.twig', [
            'form' => $form,
            'profile_role_label' => 'Participant',
            'profile_back_route' => 'participant_profile',
        ]);
    }

    #[Route('/artist/profile', name: 'artist_profile')]
    #[IsGranted('ROLE_ARTISTE')]
    public function artist(): Response
    {
        return $this->render('profile/artist.html.twig');
    }

    #[Route('/artist/profile/edit', name: 'artist_profile_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ARTISTE')]
    public function artistEdit(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $originalBirthDate = $user->getDateNaissance();
        $birthDateLocked = $originalBirthDate !== null;

        $form = $this->createForm(ParticipantProfileType::class, $user, [
            'birthdate_locked' => $birthDateLocked,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($birthDateLocked && $user->getDateNaissance() != $originalBirthDate) {
                $user->setDateNaissance($originalBirthDate);
                $this->addFlash('danger', 'La date de naissance ne peut etre modifiee qu une seule fois.');
            }

            $entityManager->flush();
            $this->addFlash('success', 'Votre profil a ete mis a jour.');

            return $this->redirectToRoute('artist_profile');
        }

        return $this->render('profile/participant_edit.html.twig', [
            'form' => $form,
            'profile_role_label' => 'Artiste',
            'profile_back_route' => 'artist_profile',
        ]);
    }
}
