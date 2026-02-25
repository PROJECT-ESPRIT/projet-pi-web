<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\User\UserType;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/user')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $query = $request->query->get('q');
        $sort = (string) $request->query->get('sort', 'nom');
        $direction = (string) $request->query->get('dir', 'asc');
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 10;

        $result = $userRepository->searchAndSortPaginated($query, $sort, $direction, $page, $perPage);
        $users = $result['items'];
        $totalPages = max(1, (int) ceil($result['total'] / $perPage));
        $page = min($page, $totalPages);

        return $this->render('user/index.html.twig', [
            'users' => $users,
            'q' => $query,
            'sort' => $sort,
            'dir' => $direction,
            'page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $plainPassword
                    )
                );
            }

            $user->setStatus(User::STATUS_APPROVED);
            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $plainPassword
                    )
                );
            }
            
            $entityManager->flush();

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/approve', name: 'app_user_approve', methods: ['POST'])]
    public function approve(User $user, Request $request, EntityManagerInterface $entityManager, EmailService $emailService): Response
    {
        if ($this->isCsrfTokenValid('approve'.$user->getId(), $request->request->get('_token'))) {
            $user->setStatus(User::STATUS_APPROVED);
            $entityManager->flush();

            try {
                $emailService->sendAccountApproved($user);
            } catch (\Exception $e) {
            }

            $this->addFlash('success', 'Utilisateur approuvé. Un email de notification a été envoyé.');
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/reject', name: 'app_user_reject', methods: ['POST'])]
    public function reject(User $user, Request $request, EntityManagerInterface $entityManager, EmailService $emailService): Response
    {
        if ($user->getStatus() === User::STATUS_APPROVED) {
            $this->addFlash('warning', 'Un compte déjà approuvé ne peut pas être refusé.');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('reject'.$user->getId(), $request->request->get('_token'))) {
            $user->setStatus(User::STATUS_REJECTED);
            $entityManager->flush();

            try {
                $emailService->sendAccountRejected($user);
            } catch (\Exception $e) {
            }

            $this->addFlash('success', 'Utilisateur refusé. Un email de notification a été envoyé.');
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/suspend', name: 'app_user_suspend', methods: ['POST'])]
    public function suspend(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('suspend'.$user->getId(), $request->request->get('_token'))) {
            $user->setStatus(User::STATUS_SUSPENDED);
            $entityManager->flush();
            $this->addFlash('success', 'Utilisateur suspendu.');
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            try {
                $username = $user->getFullName();
                $entityManager->remove($user);
                $entityManager->flush();
                $this->addFlash('success', sprintf('L\'utilisateur %s a été supprimé avec succès.', $username));
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Une erreur est survenue lors de la suppression de l\'utilisateur.');
            }
        } else {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
