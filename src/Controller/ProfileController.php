<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

<<<<<<< HEAD
=======
    #[Route('/artist/profile', name: 'artist_profile')]
    #[IsGranted('ROLE_ARTISTE')]
    public function artist(): Response
    {
        return $this->render('profile/artist.html.twig');
    }
>>>>>>> c4d1c44b0746a7387dc28bd3111400a167bda2d9
}
