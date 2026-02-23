<?php

namespace App\Controller\Donation;

use App\Entity\TypeDon;
use App\Form\Donation\TypeDonType;
use App\Repository\TypeDonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/type-don')]
#[IsGranted('ROLE_ADMIN')]
class TypeDonController extends AbstractController
{
    #[Route('/', name: 'app_type_don_index', methods: ['GET'])]
    public function index(TypeDonRepository $typeDonRepository): Response
    {
        return $this->render('type_don/index.html.twig', [
            'type_dons' => $typeDonRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_type_don_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $typeDon = new TypeDon();
        $form = $this->createForm(TypeDonType::class, $typeDon);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($typeDon);
            $entityManager->flush();

            return $this->redirectToRoute('app_type_don_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('type_don/new.html.twig', [
            'type_don' => $typeDon,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_type_don_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TypeDon $typeDon, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TypeDonType::class, $typeDon);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_type_don_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('type_don/edit.html.twig', [
            'type_don' => $typeDon,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_type_don_delete', methods: ['POST'])]
    public function delete(Request $request, TypeDon $typeDon, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$typeDon->getId(), $request->request->get('_token'))) {
            $entityManager->remove($typeDon);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_type_don_index', [], Response::HTTP_SEE_OTHER);
    }
}
