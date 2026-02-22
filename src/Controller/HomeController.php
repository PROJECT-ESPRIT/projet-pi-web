<?php

namespace App\Controller;

use App\Repository\EvenementRepository;
use App\Repository\ForumRepository;
use App\Repository\ProduitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(
        EvenementRepository $evenementRepository,
        ProduitRepository $produitRepository,
        ForumRepository $forumRepository,
    ): Response {
        $user = $this->getUser();
        $allUpcoming = $evenementRepository->findBy([], ['dateDebut' => 'ASC'], 12);

        $myEvents = [];
        $otherEvents = [];
        $isArtist = false;

        if ($user && in_array('ROLE_ARTISTE', $user->getRoles(), true)) {
            $isArtist = true;
            foreach ($allUpcoming as $ev) {
                if ($ev->getOrganisateur() && $ev->getOrganisateur()->getId() === $user->getId()) {
                    $myEvents[] = $ev;
                } else {
                    $otherEvents[] = $ev;
                }
            }
        }

        $featuredEvents = $isArtist && count($myEvents) > 0
            ? array_slice($myEvents, 0, 4)
            : array_slice($allUpcoming, 0, 4);

        return $this->render('home/index.html.twig', [
            'user' => $user,
            'isArtist' => $isArtist,
            'latestEvents' => array_slice($allUpcoming, 0, 9),
            'featuredEvents' => $featuredEvents,
            'myEvents' => array_slice($myEvents, 0, 6),
            'otherEvents' => array_slice($otherEvents, 0, 6),
            'latestProduits' => $produitRepository->findBy([], ['id' => 'DESC'], 4),
            'latestForums' => $forumRepository->findBy([], ['dateCreation' => 'DESC'], 3),
        ]);
    }
}
