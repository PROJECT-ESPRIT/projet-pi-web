<?php

namespace App\Controller;

use App\Entity\Charity;
use App\Entity\User;
use App\Form\CharityType;
use App\Repository\CharityRepository;
use App\Repository\DonationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/charity')]
class CharityController extends AbstractController
{
    #[Route('/', name: 'app_charity_index', methods: ['GET'])]
    public function index(Request $request, CharityRepository $charityRepository, DonationRepository $donationRepository): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 9;
        $total = $charityRepository->countForListing(true);
        $totalPages = max(1, (int) ceil($total / $perPage));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $charityRows = $charityRepository->findForListing(true, $page, $perPage);
        $charityIds = array_values(array_filter(array_map(
            static fn (array $row): ?int => $row['charity']->getId(),
            $charityRows
        )));

        return $this->render('charity/index.html.twig', [
            'charities' => $charityRows,
            'donations_by_charity' => $donationRepository->findRecentByCharityIds($charityIds, 12),
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/new', name: 'app_charity_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur invalide.');
        }

        $charity = new Charity();
        $charity->setCreatedBy($user);
        $form = $this->createForm(CharityType::class, $charity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $pictureFile = $form->get('pictureFile')->getData();
            if ($pictureFile instanceof UploadedFile) {
                $originalFilename = pathinfo($pictureFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = (string) $slugger->slug($originalFilename);
                $extension = $pictureFile->guessExtension() ?: 'bin';
                $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $extension;

                $targetDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/charities';
                if (!is_dir($targetDirectory)) {
                    mkdir($targetDirectory, 0775, true);
                }

                $pictureFile->move($targetDirectory, $newFilename);
                $charity->setPicture('uploads/charities/' . $newFilename);
            }

            try {
                $entityManager->persist($charity);
                $entityManager->flush();
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Échec de la création de la cause. Veuillez réessayer.');

                return $this->render('charity/new.html.twig', [
                    'charity' => $charity,
                    'form' => $form,
                ]);
            }

            $this->addFlash('sweet_success', 'Cause ajoutée avec succès.');

            return $this->redirectToRoute('app_charity_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Le formulaire contient des erreurs. Vérifiez les champs requis.');
        }

        return $this->render('charity/new.html.twig', [
            'charity' => $charity,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/reject', name: 'app_charity_reject', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reject(Request $request, Charity $charity, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('reject_charity_' . $charity->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_charity_index');
        }

        $charity->setStatus(Charity::STATUS_REJECTED);
        $entityManager->flush();

        $this->addFlash('success', 'La cause a été rejetée.');

        return $this->redirectToRoute('app_charity_index');
    }

    #[Route('/{id}/restore', name: 'app_charity_restore', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function restore(Request $request, Charity $charity, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('restore_charity_' . $charity->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_charity_index');
        }

        $charity->setStatus(Charity::STATUS_ACTIVE);
        $entityManager->flush();

        $this->addFlash('success', 'La cause a été réactivée.');

        return $this->redirectToRoute('app_charity_index');
    }
}
