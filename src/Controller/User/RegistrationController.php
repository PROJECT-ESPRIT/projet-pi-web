<?php

namespace App\Controller\User;

use App\Entity\User;
use App\Form\User\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\EmailService;
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
        EntityManagerInterface $entityManager,
        EmailService $emailService,
        UserRepository $userRepository,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $existing = $userRepository->findOneBy(['email' => $email]);

            if ($existing && $existing->getStatus() === User::STATUS_EMAIL_PENDING) {
                $existing->generateEmailVerificationToken();
                $entityManager->flush();

                try {
                    $emailService->sendEmailVerification($existing);
                    $this->addFlash('success', 'Un nouveau lien de vérification a été envoyé à ' . $email);
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'Erreur d\'envoi : ' . $e->getMessage());
                }

                return $this->redirectToRoute('app_registration_confirmation', ['email' => $email]);
            }

            if ($existing) {
                $this->addFlash('danger', 'Un compte avec cet email existe déjà.');
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }

            $selectedRole = $form->get('role')->getData();
            $user->setRoles([$selectedRole]);
            $user->setStatus(User::STATUS_EMAIL_PENDING);
            $user->generateEmailVerificationToken();

            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Send verification email before persisting so it is sent in the same request with correct token
            try {
                $emailService->sendEmailVerification($user);
            } catch (\Throwable $e) {
                $this->addFlash('warning', 'Erreur d\'envoi : ' . $e->getMessage());
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Un email de vérification a été envoyé à ' . $email);
            return $this->redirectToRoute('app_registration_confirmation', ['email' => $email]);
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/register/confirmation', name: 'app_registration_confirmation')]
    public function confirmation(Request $request): Response
    {
        return $this->render('registration/confirmation.html.twig', [
            'email' => $request->query->get('email', ''),
        ]);
    }

    #[Route('/verify-email/{token}', name: 'app_verify_email')]
    public function verifyEmail(
        string $token,
        EntityManagerInterface $entityManager,
        int $emailVerificationTtlHours,
    ): Response {
        $user = $entityManager->getRepository(User::class)->findOneBy([
            'emailVerificationToken' => $token,
        ]);

        if (!$user) {
            $this->addFlash('danger', 'Lien de vérification invalide.');
            return $this->redirectToRoute('login');
        }

        if ($user->isEmailVerificationTokenExpired($emailVerificationTtlHours)) {
            $this->addFlash('warning', 'Ce lien de vérification a expiré. Veuillez demander un nouveau lien.');
            return $this->redirectToRoute('app_registration_confirmation', ['email' => $user->getEmail()]);
        }

        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationSentAt(null);

        $hasRoleNeedingApproval = array_intersect(
            $user->getRoles(),
            ['ROLE_ARTISTE', 'ROLE_PARTICIPANT']
        );

        if ($hasRoleNeedingApproval) {
            $user->setStatus(User::STATUS_EMAIL_VERIFIED);
        } else {
            $user->setStatus(User::STATUS_APPROVED);
        }

        $entityManager->flush();

        return $this->render('registration/email_verified.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/resend-verification', name: 'app_resend_verification', methods: ['POST'])]
    public function resendVerification(
        Request $request,
        EntityManagerInterface $entityManager,
        EmailService $emailService,
        UserRepository $userRepository,
    ): Response {
        $email = $request->request->get('email', '');
        $redirectTo = $request->request->get('_redirect', 'app_registration_confirmation');
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user || $user->getStatus() !== User::STATUS_EMAIL_PENDING) {
            $this->addFlash('info', 'Aucun compte en attente de vérification pour cet email.');
            return $this->redirectToRoute($redirectTo);
        }

        $user->generateEmailVerificationToken();
        $entityManager->flush();

        try {
            $emailService->sendEmailVerification($user);
            $this->addFlash('success', 'Un nouveau lien de vérification a été envoyé à ' . $email);
        } catch (\Exception $e) {
            $this->addFlash('warning', 'Erreur d\'envoi : ' . $e->getMessage());
        }

        $params = $redirectTo === 'app_registration_confirmation' ? ['email' => $email] : [];
        return $this->redirectToRoute($redirectTo, $params);
    }
}
