<?php

namespace App\Controller\Shop;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Form\Shop\ProduitType;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/boutique')]
class ProduitController extends AbstractController
{
    // ======================== LISTE ADMIN ========================
    #[Route('/admin', name: 'app_produit_admin_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminIndex(ProduitRepository $produitRepository, Request $request): Response
    {
        // Construction de la QueryBuilder
        $qb = $produitRepository->createQueryBuilder('p');

        // ================= FILTRES =================
        if ($search = $request->query->get('search')) {
            $qb->andWhere('p.nom LIKE :search')
               ->setParameter('search', '%'.$search.'%');
        }
        if ($minPrice = $request->query->get('min_price')) {
            $qb->andWhere('p.prix >= :min_price')
               ->setParameter('min_price', $minPrice);
        }
        if ($maxPrice = $request->query->get('max_price')) {
            $qb->andWhere('p.prix <= :max_price')
               ->setParameter('max_price', $maxPrice);
        }
        if ($minStock = $request->query->get('min_stock')) {
            $qb->andWhere('p.stock >= :min_stock')
               ->setParameter('min_stock', $minStock);
        }

        // ================= TRI SÉCURISÉ =================
        $sort = $request->query->get('sort', 'p.id');
        $direction = strtoupper($request->query->get('direction', 'DESC'));
        $allowedSorts = ['p.id','p.nom','p.prix','p.stock'];
        $allowedDirections = ['ASC','DESC'];
        if (!in_array($sort, $allowedSorts)) $sort = 'p.id';
        if (!in_array($direction, $allowedDirections)) $direction = 'DESC';

        $qb->orderBy($sort, $direction);

        // Récupération des produits (sans pagination)
        $produits = $qb->getQuery()->getResult();

        return $this->render('produit/admin_index.html.twig', [
            'produits' => $produits, // ✅ liste complète
        ]);
    }

    // ======================== NOUVEAU PRODUIT ========================
    #[Route('/admin/new', name: 'app_produit_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $produit = new Produit();
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = transliterator_transliterate(
                    'Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()',
                    $originalFilename
                );
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('produit_images_directory'),
                        $newFilename
                    );
                    $produit->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload de l\'image.');
                }
            }

            $entityManager->persist($produit);
            $entityManager->flush();

            $this->addFlash('success', 'Produit créé avec succès !');

            return $this->redirectToRoute('app_produit_admin_index');
        }

        return $this->render('produit/new.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    // ======================== AFFICHER PRODUIT ========================
    #[Route('/produit/{id}', name: 'app_produit_show', methods: ['GET'])]
    public function show(Produit $produit): Response
    {
        return $this->render('produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    // ======================== MODIFIER PRODUIT ========================
    #[Route('/admin/{id}/edit', name: 'app_produit_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = transliterator_transliterate(
                    'Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()',
                    $originalFilename
                );
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('produit_images_directory'),
                        $newFilename
                    );
                    $produit->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload de l\'image.');
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Produit mis à jour avec succès !');

            return $this->redirectToRoute('app_produit_admin_index');
        }

        return $this->render('produit/edit.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    // ======================== SUPPRESSION ========================
    #[Route('/admin/{id}', name: 'app_produit_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$produit->getId(), $request->request->get('_token'))) {
            $entityManager->remove($produit);
            $entityManager->flush();
            $this->addFlash('success', 'Produit supprimé avec succès !');
        }

        return $this->redirectToRoute('app_produit_admin_index');
    }

    // ======================== COMMANDER PRODUIT ========================
    #[Route('/commander/{id}', name: 'app_produit_commander', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function commander(Produit $produit, EntityManagerInterface $entityManager): Response
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

        $entityManager->persist($commande);
        $entityManager->flush();

        $this->addFlash('success', 'Votre commande a été passée avec succès !');

        return $this->redirectToRoute('app_commande_my');
    }
}