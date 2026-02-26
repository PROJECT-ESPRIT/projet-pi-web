<?php

namespace App\Controller;

use App\Form\LoginFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $returnTo = $this->getSafeReturnTo($request);

        // Redirect if already logged in
        if ($this->getUser()) {
            if ($returnTo !== null) {
                return $this->redirect($returnTo);
            }

            return $this->redirectToRoute('home');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        $form = $this->createForm(LoginFormType::class);

        return $this->render('security/login.html.twig', [
            'loginForm' => $form,
            'last_username' => $lastUsername,
            'error' => $error,
            'return_to' => $returnTo,
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    private function getSafeReturnTo(Request $request): ?string
    {
        $candidate = trim((string) $request->query->get('return_to', ''));
        if ($candidate === '') {
            return null;
        }

        if (!str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            return null;
        }

        return $candidate;
    }
}
