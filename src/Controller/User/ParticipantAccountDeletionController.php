<?php

namespace App\Controller\User;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AccountDeletionLinkSigner;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ParticipantAccountDeletionController extends AbstractController
{
    #[Route('/participant/account/delete-request', name: 'participant_account_delete_request', methods: ['POST'])]
    #[IsGranted('ROLE_PARTICIPANT')]
    public function requestDelete(
        Request $request,
        AccountDeletionLinkSigner $linkSigner,
        EmailService $emailService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('participant_delete_request', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('participant_profile');
        }

        $params = $linkSigner->generateFor($user);
        $confirmationUrl = $this->generateUrl('participant_account_delete_confirm', $params, UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $emailService->sendParticipantDeleteConfirmation(
                $user,
                $confirmationUrl,
                $linkSigner->getTtlMinutes()
            );
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Impossible d envoyer le mail de confirmation pour le moment.');
            return $this->redirectToRoute('participant_profile');
        }

        $this->addFlash('success', 'Un email de confirmation de suppression a ete envoye.');
        return $this->redirectToRoute('participant_profile');
    }

    #[Route('/participant/account/delete-confirm', name: 'participant_account_delete_confirm', methods: ['GET'])]
    public function confirmDelete(
        Request $request,
        UserRepository $userRepository,
        AccountDeletionLinkSigner $linkSigner,
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage,
    ): Response {
        $uid = (int) $request->query->get('uid', 0);
        $exp = (int) $request->query->get('exp', 0);
        $sig = (string) $request->query->get('sig', '');

        if ($uid <= 0 || $exp <= 0 || $sig === '') {
            $this->addFlash('danger', 'Lien de suppression invalide.');
            return $this->redirectToRoute('home');
        }

        $user = $userRepository->find($uid);
        if (!$user instanceof User || !in_array('ROLE_PARTICIPANT', $user->getRoles(), true)) {
            $this->addFlash('danger', 'Compte participant introuvable.');
            return $this->redirectToRoute('home');
        }

        if (!$linkSigner->isValid($user, $exp, $sig)) {
            $this->addFlash('danger', 'Lien de suppression invalide ou expire.');
            return $this->redirectToRoute('home');
        }

        $current = $this->getUser();
        if ($current instanceof User && $current->getId() === $user->getId()) {
            $tokenStorage->setToken(null);
            if ($request->hasSession()) {
                $request->getSession()->invalidate();
            }
        }

        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('success', 'Votre compte participant a ete supprime avec succes.');
        return $this->redirectToRoute('home');
    }
}
