<?php

namespace App\Controller\Panier;

use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/panier')]
class PanierController extends AbstractController
{
    /**
     * 🛒 Affichage du panier
     */
    #[Route('/', name: 'app_panier_index', methods: ['GET'])]
    public function index(CartService $cartService): Response
    {
        return $this->render('panier/index.html.twig', [
            'cart' => $cartService->getCart(),
            'total' => $cartService->getTotal(),
        ]);
    }

    /**
     * ➕ Ajouter un produit au panier
     * Compatible AJAX + redirection classique
     */
    #[Route('/add/{id}', name: 'app_panier_add', methods: ['POST'])]
    public function add(
        int $id,
        CartService $cartService,
        Request $request
    ): Response {

        $cartService->add($id);

        // Si requête AJAX → réponse JSON
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'totalItems' => $cartService->getTotalQuantity(),
                'totalPrice' => $cartService->getTotal(),
            ]);
        }

        $this->addFlash('success', 'Produit ajouté au panier ✅');

        return $this->redirectToRoute('app_panier_index');
    }

    /**
     * ➖ Supprimer un produit
     */
    #[Route('/remove/{id}', name: 'app_panier_remove', methods: ['POST'])]
    public function remove(
        int $id,
        CartService $cartService,
        Request $request
    ): Response {

        $cartService->remove($id);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'totalItems' => $cartService->getTotalQuantity(),
                'totalPrice' => $cartService->getTotal(),
            ]);
        }

        $this->addFlash('info', 'Produit retiré du panier.');

        return $this->redirectToRoute('app_panier_index');
    }

    /**
     * 🗑 Vider le panier
     */
    #[Route('/clear', name: 'app_panier_clear', methods: ['POST'])]
    public function clear(
        CartService $cartService,
        Request $request
    ): Response {

        $cartService->clear();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'totalItems' => 0,
                'totalPrice' => 0,
            ]);
        }

        $this->addFlash('warning', 'Panier vidé.');

        return $this->redirectToRoute('app_panier_index');
    }
}