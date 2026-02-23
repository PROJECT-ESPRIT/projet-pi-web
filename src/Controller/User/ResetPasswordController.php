<?php

namespace App\Controller\User;

use App\Entity\PasswordResetToken;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use App\Service\ResetPasswordMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ResetPasswordController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        PasswordResetTokenRepository $tokenRepository,
        EntityManagerInterface $em,
        ResetPasswordMailer $mailer,
        LoggerInterface $logger,
        int $passwordResetTtlMinutes,
    ): Response {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email'));
            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user) {
                $tokenRepository->markAllUserTokensAsUsed($user);

                $code = (string) random_int(100000, 999999);

                $token = (new PasswordResetToken())
                    ->setUser($user)
                    ->setTokenHash(hash('sha256', $code))
                    ->setExpiresAt(new \DateTimeImmutable("+{$passwordResetTtlMinutes} minutes"));

                $em->persist($token);
                $em->flush();

                try {
                    $mailer->send($user->getEmail(), $code);
                } catch (TransportExceptionInterface $e) {
                    $logger->error('Password reset email failed', [
                        'email' => $user->getEmail(),
                        'error' => $e->getMessage(),
                    ]);

                    if ('dev' === $this->getParameter('kernel.environment')) {
                        $this->addFlash(
                            'info',
                            sprintf('SMTP non configure. Code de test: %s', $code)
                        );

                        return $this->redirectToRoute('app_reset_password_code', [
                            'email' => $email,
                        ]);
                    }

                    $this->addFlash('error', 'Impossible d\'envoyer le code pour le moment. Reessayez plus tard.');
                    return $this->redirectToRoute('app_forgot_password');
                }
            }

            $this->addFlash(
                'success',
                'Si ce compte existe, un code de vérification a été envoyé.'
            );

            return $this->redirectToRoute('app_reset_password_code', [
                'email' => $email
            ]);
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/reset-password-code', name: 'app_reset_password_code', methods: ['GET', 'POST'])]
    public function resetPasswordCode(
        Request $request,
        UserRepository $userRepository,
        PasswordResetTokenRepository $tokenRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        $email = (string) $request->get('email');
        $errors = [];

        if ($request->isMethod('POST')) {
            $code = trim((string) $request->request->get('code'));
            $password = (string) $request->request->get('password');
            $confirm = (string) $request->request->get('confirm_password');

            if ($password !== $confirm) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }

            $user = $userRepository->findOneBy(['email' => $email]);
            if (!$user) {
                $errors[] = 'Utilisateur introuvable.';
            }

            $token = $user ? $tokenRepository->findValidByUserAndCode($user, $code) : null;
            if (!$token) {
                $errors[] = 'Code invalide ou expiré.';
            }

            if (!$errors) {
                $user->setPassword(
                    $passwordHasher->hashPassword($user, $password)
                );
                $token->setUsedAt(new \DateTimeImmutable());
                $em->flush();

                $this->addFlash('success', 'Mot de passe modifié avec succès.');
                return $this->redirectToRoute('login');
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'errors' => $errors,
            'prefill_email' => $email
        ]);
    }
}
