<?php
// src/Service/CartService.php
namespace App\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Repository\ProduitRepository;

class CartService
{
    private $session;
    private $produitRepository;

    public function __construct(SessionInterface $session, ProduitRepository $produitRepository)
    {
        $this->session = $session;
        $this->produitRepository = $produitRepository;
    }

    // Récupérer le panier complet
    public function getCart(): array
    {
        $cart = $this->session->get('cart', []);
        $cartWithData = [];

        foreach ($cart as $id => $quantity) {
            $produit = $this->produitRepository->find($id);
            if ($produit) {
                $cartWithData[] = [
                    'produit' => $produit,
                    'quantity' => $quantity
                ];
            }
        }

        return $cartWithData;
    }

    // Ajouter un produit au panier
    public function add(int $id)
    {
        $cart = $this->session->get('cart', []);
        if (!empty($cart[$id])) {
            $cart[$id]++;
        } else {
            $cart[$id] = 1;
        }
        $this->session->set('cart', $cart);
    }

    // Supprimer un produit du panier
    public function remove(int $id)
    {
        $cart = $this->session->get('cart', []);
        if (!empty($cart[$id])) {
            unset($cart[$id]);
        }
        $this->session->set('cart', $cart);
    }

    // Vider le panier
    public function clear()
    {
        $this->session->remove('cart');
    }

    // Calculer le total
    public function getTotal(): float
    {
        $total = 0;
        foreach ($this->getCart() as $item) {
            $total += $item['produit']->getPrix() * $item['quantity'];
        }
        return $total;
    }

    // Compter le nombre d’articles
    public function getQuantity(): int
    {
        $cart = $this->session->get('cart', []);
        return array_sum($cart);
    }
}