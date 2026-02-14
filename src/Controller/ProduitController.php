<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/boutique')]
class ProduitController extends AbstractController
{

   /*
|------------------------------------------------------------------
| ✅ BOUTIQUE PUBLIQUE AVEC PAGINATION
|------------------------------------------------------------------
*/

#[Route('/', name: 'app_produit_index', methods: ['GET'])]
public function index(
    Request $request,
    ProduitRepository $produitRepository,
    PaginatorInterface $paginator
): Response {

    $query = $produitRepository->createQueryBuilder('p')
        ->orderBy('p.id', 'DESC')
        ->getQuery();

    $produits = $paginator->paginate(
        $query,
        $request->query->getInt('page', 1),
        6 // produits par page
    );

    return $this->render('produit/index.html.twig', [
        'produits' => $produits,
    ]);
}


    /*
    |------------------------------------------------------------------
    | ✅ ADMIN LISTE PRODUITS (PAGINATION)
    |------------------------------------------------------------------
    */

    #[Route('/admin', name: 'app_produit_admin_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminIndex(
        Request $request,
        ProduitRepository $produitRepository,
        PaginatorInterface $paginator
    ): Response {

        $query = $produitRepository->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->getQuery();

        $produits = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            5
        );

        return $this->render('produit/admin_index.html.twig', [
            'produits' => $produits,
        ]);
    }

    /*
    |------------------------------------------------------------------
    | ✅ CREATE AVEC UPLOAD IMAGE
    |------------------------------------------------------------------
    */

    #[Route('/admin/new', name: 'app_produit_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {

        $produit = new Produit();
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $imageFile = $form->get('image')->getData();

            if ($imageFile) {

                $originalFilename = pathinfo(
                    $imageFile->getClientOriginalName(),
                    PATHINFO_FILENAME
                );

                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload.');
                }

                $produit->setImage($newFilename);
            }

            $entityManager->persist($produit);
            $entityManager->flush();

            $this->addFlash('success', 'Produit ajouté avec succès.');

            return $this->redirectToRoute('app_produit_admin_index');
        }

        return $this->render('produit/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /*
    |------------------------------------------------------------------
    | ✅ EDIT AVEC REMPLACEMENT IMAGE
    |------------------------------------------------------------------
    */

    #[Route('/admin/{id}/edit', name: 'app_produit_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(
        Request $request,
        Produit $produit,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {

        $ancienneImage = $produit->getImage();

        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $imageFile = $form->get('image')->getData();

            if ($imageFile) {

                if ($ancienneImage && file_exists(
                    $this->getParameter('images_directory').'/'.$ancienneImage
                )) {
                    unlink($this->getParameter('images_directory').'/'.$ancienneImage);
                }

                $originalFilename = pathinfo(
                    $imageFile->getClientOriginalName(),
                    PATHINFO_FILENAME
                );

                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur upload image.');
                }

                $produit->setImage($newFilename);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Produit modifié.');

            return $this->redirectToRoute('app_produit_admin_index');
        }

        return $this->render('produit/edit.html.twig', [
            'form' => $form->createView(),
            'produit' => $produit,
        ]);
    }

    /*
|------------------------------------------------------------------
| ✅ SHOW PRODUIT
|------------------------------------------------------------------
*/

#[Route('/{id}', name: 'app_produit_show', methods: ['GET'])]
public function show(Produit $produit): Response
{
    return $this->render('produit/show.html.twig', [
        'produit' => $produit,
    ]);
}


    /*
    |------------------------------------------------------------------
    | ✅ DELETE AVEC SUPPRESSION IMAGE
    |------------------------------------------------------------------
    */

    #[Route('/admin/{id}', name: 'app_produit_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        Request $request,
        Produit $produit,
        EntityManagerInterface $entityManager
    ): Response {

        if ($this->isCsrfTokenValid(
            'delete'.$produit->getId(),
            $request->request->get('_token')
        )) {

            if ($produit->getImage() && file_exists(
                $this->getParameter('images_directory').'/'.$produit->getImage()
            )) {
                unlink($this->getParameter('images_directory').'/'.$produit->getImage());
            }

            $entityManager->remove($produit);
            $entityManager->flush();

            $this->addFlash('success', 'Produit supprimé.');
        }

        return $this->redirectToRoute('app_produit_admin_index');
    }
}
