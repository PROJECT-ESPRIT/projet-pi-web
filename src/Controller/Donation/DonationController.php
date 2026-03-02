<?php

namespace App\Controller\Donation;

use App\Entity\Donation;
use App\Entity\User;
use App\Repository\CharityRepository;
use App\Repository\DonationRepository;
use App\Repository\TypeDonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\EmailService;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/donation')]
class DonationController extends AbstractController
{
    #[Route('/test-email', name: 'app_test_email', methods: ['GET'])]
    public function testEmail(EmailService $emailService): Response
    {
        try {
            $emailService->sendConfirmationEmail(
                'yasminemaatougui9@gmail.com',
                'Yasmine Maatougui',
                'Atelier de peinture',
                new \DateTime('+3 days'),
                'Espace Culturel, 123 Rue de l\'Art, 75001 Paris'
            );
            
            return new JsonResponse(['status' => 'success', 'message' => 'Email de test envoyé avec succès !']);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
    
    #[Route('/my-donations', name: 'app_donation_my', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myDonations(DonationRepository $donationRepository): Response
    {
        return $this->render('donation/my_donations.html.twig', [
            'donations' => $donationRepository->findByDonateurVisible($this->getUser()),
        ]);
    }

    #[Route('/new', name: 'app_donation_new', methods: ['GET', 'POST'])]
    public function new(): Response
    {
        return $this->redirectToRoute('app_charity_index');
    }

    #[Route('/admin', name: 'app_donation_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request, DonationRepository $donationRepository): Response
    {
        $search = $request->query->getString('q');
        $sort = $request->query->getString('sort', 'dateDon');
        $direction = $request->query->getString('direction', 'DESC');

        return $this->render('donation/index.html.twig', [
            'donations' => $donationRepository->findBySearchAndSort($search, $sort, $direction, true),
            'search' => $search,
            'sort' => $sort,
            'direction' => strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC',
        ]);
    }

    #[Route('/payment/success', name: 'app_donation_payment_success', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function paymentSuccess(
        Request $request,
        CharityRepository $charityRepository,
        TypeDonRepository $typeDonRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $pendingId = $request->query->getString('pending_id');
        $sessionId = $request->query->getString('session_id');
        $lookupKey = $pendingId !== '' ? $pendingId : $sessionId;
        if ($lookupKey === '') {
            $this->addFlash('danger', 'Session de paiement introuvable.');
            return $this->redirectToRoute('app_charity_index');
        }

        $pending = $request->getSession()->get('pending_donations', []);
        if (!isset($pending[$lookupKey])) {
            $this->addFlash('info', 'Le paiement a déjà été traité ou a expiré.');
            return $this->redirectToRoute('app_charity_index');
        }

        $data = $pending[$lookupKey];
        unset($pending[$lookupKey]);
        $request->getSession()->set('pending_donations', $pending);

        if ($sessionId !== '' && isset($data['stripe_session_id']) && $data['stripe_session_id'] !== $sessionId) {
            $this->addFlash('danger', 'Session de paiement invalide.');
            $this->cleanupDonationFile($data['image_path'] ?? null);
            return $this->redirectToRoute('app_charity_index');
        }

        $user = $this->getUser();
        if (!$user instanceof User || (int) ($data['user_id'] ?? 0) !== $user->getId()) {
            $this->addFlash('danger', 'Impossible de finaliser le don pour cet utilisateur.');
            $this->cleanupDonationFile($data['image_path'] ?? null);
            return $this->redirectToRoute('app_charity_index');
        }

        $charity = $charityRepository->find((int) ($data['charity_id'] ?? 0));
        if (!$charity || $charity->isHidden()) {
            $this->addFlash('danger', 'Cette charity n’est plus disponible.');
            $this->cleanupDonationFile($data['image_path'] ?? null);
            return $this->redirectToRoute('app_charity_index');
        }
        if ($charity->getOwner() && $charity->getOwner()->getId() === $user->getId()) {
            $this->addFlash('danger', 'Vous ne pouvez pas donner à votre propre charity.');
            $this->cleanupDonationFile($data['image_path'] ?? null);
            return $this->redirectToRoute('app_charity_index');
        }

        $type = $typeDonRepository->find((int) ($data['type_id'] ?? 0));
        if (!$type) {
            $this->addFlash('danger', 'Type de don introuvable.');
            $this->cleanupDonationFile($data['image_path'] ?? null);
            return $this->redirectToRoute('app_charity_index');
        }
        $donation = new Donation();
        $donation->setCharity($charity);
        $donation->setType($type);
        $donation->setDescription($data['description'] ?? null);
        $donation->setIsAnonymous((bool) ($data['is_anonymous'] ?? false));
        $donation->setAmount((int) ($data['amount'] ?? 0));
        $donation->setDonateur($user);
        $donation->setImagePath($data['image_path'] ?? null);

        $entityManager->persist($donation);
        $entityManager->flush();

        $this->addFlash('payment_success', 'Payment succeeded ! Thank you for your support <3');
        $this->addFlash('success', 'Merci pour votre don à ' . $charity->getName() . ' !');

        $route = $data['return_route'] ?? 'app_charity_index';
        $params = $data['return_params'] ?? [];
        return $this->redirectToRoute($route, $params);
    }

    #[Route('/payment/cancel', name: 'app_donation_payment_cancel', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function paymentCancel(Request $request): Response
    {
        $pendingId = $request->query->getString('pending_id');
        $sessionId = $request->query->getString('session_id');
        $lookupKey = $pendingId !== '' ? $pendingId : $sessionId;
        $pending = $request->getSession()->get('pending_donations', []);
        if ($lookupKey !== '' && isset($pending[$lookupKey])) {
            $data = $pending[$lookupKey];
            unset($pending[$lookupKey]);
            $request->getSession()->set('pending_donations', $pending);
            $this->cleanupDonationFile($data['image_path'] ?? null);

            $route = $data['return_route'] ?? 'app_charity_index';
            $params = $data['return_params'] ?? [];
            $this->addFlash('info', 'Paiement annulé.');
            return $this->redirectToRoute($route, $params);
        }

        $this->addFlash('info', 'Paiement annulé.');
        return $this->redirectToRoute('app_charity_index');
    }

    private function cleanupDonationFile(?string $path): void
    {
        if (!$path) {
            return;
        }
        $root = $this->getParameter('kernel.project_dir');
        $fullPath = $root . '/public' . $path;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
