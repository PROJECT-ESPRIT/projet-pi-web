<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use App\Service\LoyaltyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/boutique')]
class ProduitController extends AbstractController
{
    #[Route('/', name: 'app_produit_index', methods: ['GET'])]
    public function index(ProduitRepository $produitRepository): Response
    {
        return $this->render('produit/index.html.twig', [
            'produits' => $produitRepository->findAll(),
        ]);
    }

    #[Route('/admin', name: 'app_produit_admin_index', methods: ['GET'])]
    public function adminIndex(Request $request, ProduitRepository $produitRepository): Response
    {
        $this->denyAccessUnlessCanManage();

        [$search, $sort, $direction] = $this->validateFilters(
            $request,
            ['id', 'nom', 'prix', 'stock'],
            'id',
            'asc'
        );

        return $this->render('produit/admin_index.html.twig', [
            'produits' => $produitRepository->findBySearchAndSort($search, $sort, $direction),
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    #[Route('/admin/new', name: 'app_produit_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessCanManage();

        $produit = new Produit();
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($produit);
            $entityManager->flush();

            return $this->redirectToRoute('app_produit_admin_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('produit/new.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    #[Route('/produit/{id}', name: 'app_produit_show', methods: ['GET'])]
    public function show(Produit $produit): Response
    {
        return $this->render('produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/admin/{id}/edit', name: 'app_produit_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessCanManage();

        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_produit_admin_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('produit/edit.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    #[Route('/admin/{id}', name: 'app_produit_delete', methods: ['POST'])]
    public function delete(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessCanManage();

        if ($this->isCsrfTokenValid('delete'.$produit->getId(), $request->request->get('_token'))) {
            $entityManager->remove($produit);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_produit_admin_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/commander/{id}', name: 'app_produit_commander', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function commander(Produit $produit, EntityManagerInterface $entityManager, LoyaltyService $loyaltyService): Response
    {
        if ($produit->getStock() <= 0) {
            $this->addFlash('danger', 'Ce produit est en rupture de stock.');
            return $this->redirectToRoute('app_produit_show', ['id' => $produit->getId()]);
        }

        $commande = new Commande();
        $commande->setUser($this->getUser());
        $commande->setStatut('EN_ATTENTE');
        $commande->setTotal($produit->getPrix());

        $ligne = new LigneCommande();
        $ligne->setProduit($produit);
        $ligne->setQuantite(1);
        $ligne->setPrixUnitaire($produit->getPrix());

        $commande->addLigneCommande($ligne);

        $produit->setStock($produit->getStock() - 1);

        $user = $commande->getUser();
        if ($user !== null) {
            $loyaltyService->awardPoints($user, LoyaltyService::POINTS_COMMANDE);
        }

        $entityManager->persist($commande);
        $entityManager->flush();

        $this->addFlash('success', 'Votre commande a ete passee avec succes ! +'.LoyaltyService::POINTS_COMMANDE.' points fidelite.');

        return $this->redirectToRoute('app_commande_my');
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function validateFilters(
        Request $request,
        array $allowedSorts,
        string $defaultSort,
        string $defaultDirection
    ): array {
        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', $defaultSort);
        $direction = strtolower((string) $request->query->get('direction', $defaultDirection));

        if ($search !== '' && (mb_strlen($search) > 100 || !preg_match('/^[\p{L}\p{N}\s._\-\'@]+$/u', $search))) {
            $this->addFlash('error', 'Recherche invalide. Utilisez uniquement lettres, chiffres, espaces et . _ - @ \' .');
            $search = '';
        }

        if (!in_array($sort, $allowedSorts, true)) {
            $this->addFlash('error', 'Champ de tri invalide.');
            $sort = $defaultSort;
        }

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $this->addFlash('error', 'Direction de tri invalide.');
            $direction = $defaultDirection;
        }

        return [$search, $sort, $direction];
    }

    private function denyAccessUnlessCanManage(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_ARTISTE')) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }
    }
}
