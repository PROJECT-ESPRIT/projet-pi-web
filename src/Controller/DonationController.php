<?php

namespace App\Controller;

use App\Repository\DonationRepository;
use App\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
}
