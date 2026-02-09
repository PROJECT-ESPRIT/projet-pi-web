<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/events')]
class EvenementController extends AbstractController
{
    #[Route('/', name: 'app_evenement_index', methods: ['GET'])]
    public function index(Request $request, EvenementRepository $evenementRepository): Response
    {
        $filterInput = [
            'q' => trim((string) $request->query->get('q', '')),
            'lieu' => trim((string) $request->query->get('lieu', '')),
            'date_start' => (string) $request->query->get('date_start', ''),
            'date_end' => (string) $request->query->get('date_end', ''),
            'prix_min' => (string) $request->query->get('prix_min', ''),
            'prix_max' => (string) $request->query->get('prix_max', ''),
            'sort' => (string) $request->query->get('sort', 'date_asc'),
        ];

        $filters = [
            'q' => $filterInput['q'],
            'lieu' => $filterInput['lieu'],
            'date_start' => $this->parseDate($filterInput['date_start']),
            'date_end' => $this->parseDate($filterInput['date_end'], true),
            'prix_min' => $this->parseFloat($filterInput['prix_min']),
            'prix_max' => $this->parseFloat($filterInput['prix_max']),
            'sort' => $filterInput['sort'],
        ];

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 9;
        $paginator = $evenementRepository->searchAndSort($filters, $page, $perPage);
        $total = count($paginator);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $paginator = $evenementRepository->searchAndSort($filters, $page, $perPage);
        }

        return $this->render('evenement/index.html.twig', [
            'evenements' => iterator_to_array($paginator, false),
            'filters' => $filters,
            'filter_input' => $filterInput,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/new', name: 'app_evenement_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ARTISTE')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $evenement->setOrganisateur($this->getUser());
            $entityManager->persist($evenement);
            $entityManager->flush();

            $this->addFlash('success', 'Événement créé avec succès !');

            return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('evenement/new.html.twig', [
            'evenement' => $evenement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_evenement_show', methods: ['GET'])]
    public function show(Evenement $evenement, ReservationRepository $reservationRepository): Response
    {
        $hasReserved = false;
        if ($this->getUser()) {
            $reservation = $reservationRepository->findOneBy([
                'evenement' => $evenement,
                'participant' => $this->getUser()
            ]);
            $hasReserved = $reservation !== null;
        }

        return $this->render('evenement/show.html.twig', [
            'evenement' => $evenement,
            'hasReserved' => $hasReserved,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_evenement_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ARTISTE')]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        // Check if the current user is the organizer
        if ($evenement->getOrganisateur() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cet événement.');
        }

        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Événement modifié avec succès !');

            return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('evenement/edit.html.twig', [
            'evenement' => $evenement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_evenement_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ARTISTE')]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        if ($evenement->getOrganisateur() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer cet événement.');
        }

        if ($this->isCsrfTokenValid('delete'.$evenement->getId(), $request->request->get('_token'))) {
            $entityManager->remove($evenement);
            $entityManager->flush();
            $this->addFlash('success', 'Événement supprimé avec succès !');
        }

        return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
    }

    private function parseDate(?string $value, bool $endOfDay = false): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false) {
            return null;
        }

        if ($endOfDay) {
            return $date->setTime(23, 59, 59);
        }

        return $date->setTime(0, 0, 0);
    }

    private function parseFloat(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
