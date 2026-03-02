<?php

namespace App\Controller;

use App\Entity\TypeDon;
use App\Form\TypeDonType;
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
    public function index(Request $request, TypeDonRepository $typeDonRepository): Response
    {
        [$search, $sort, $direction] = $this->validateFilters(
            $request,
            ['id', 'libelle'],
            'id',
            'asc'
        );

        return $this->render('type_don/index.html.twig', [
            'type_dons' => $typeDonRepository->findBySearchAndSort($search, $sort, $direction),
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
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
            $this->addFlash('error', 'Recherche invalide. Utilisez uniquement lettres, chiffres, espaces et . _ - @ \'.');
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
}
