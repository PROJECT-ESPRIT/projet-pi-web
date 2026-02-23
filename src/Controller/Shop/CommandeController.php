<?php

namespace App\Controller\Shop;

use App\Repository\CommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commandes')]
class CommandeController extends AbstractController
{
    #[Route('/mes-commandes', name: 'app_commande_my', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myOrders(CommandeRepository $commandeRepository): Response
    {
        return $this->render('commande/my_orders.html.twig', [
            'commandes' => $commandeRepository->findBy(['user' => $this->getUser()], ['dateCommande' => 'DESC']),
        ]);
    }

    #[Route('/admin', name: 'app_commande_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(CommandeRepository $commandeRepository): Response
    {
        return $this->render('commande/index.html.twig', [
            'commandes' => $commandeRepository->findAll(),
        ]);
    }
}
