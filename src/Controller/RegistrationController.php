<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $returnTo = $this->getSafeReturnTo($request);

        // Redirect if already logged in
        if ($this->getUser()) {
            if ($returnTo !== null) {
                return $this->redirect($returnTo);
            }

            return $this->redirectToRoute('home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get the selected role from the form
            $selectedRole = $form->get('role')->getData();
            
            // Set the user's role
            $user->setRoles([$selectedRole]);

            if ($selectedRole === 'ROLE_ARTISTE') {
                $user->setStatus(User::STATUS_PENDING);
            }
            
            // Encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Inscription réussie ! Vous pouvez maintenant vous connecter.');

            if ($returnTo !== null) {
                return $this->redirectToRoute('login', ['return_to' => $returnTo]);
            }

            return $this->redirectToRoute('login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
            'return_to' => $returnTo,
        ]);
    }

    private function getSafeReturnTo(Request $request): ?string
    {
        $candidate = trim((string) $request->query->get('return_to', ''));
        if ($candidate === '') {
            $candidate = trim((string) $request->request->get('return_to', ''));
        }

        if ($candidate === '') {
            return null;
        }

        if (!str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            return null;
        }

        return $candidate;
    }
}
