<?php

namespace App\Controller;

use App\Entity\Donation;
use App\Entity\User;
use App\Form\DonationType;
use App\Repository\DonationRepository;
use App\Service\LoyaltyService;
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
            'donations' => $donationRepository->findBy(['donateur' => $this->getUser()], ['dateDon' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_donation_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $entityManager, LoyaltyService $loyaltyService): Response
    {
        $donation = new Donation();
        $form = $this->createForm(DonationType::class, $donation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw $this->createAccessDeniedException('Utilisateur invalide.');
            }

            $donation->setDonateur($user);
            $loyaltyService->awardPoints($user, LoyaltyService::POINTS_DONATION);
            $entityManager->persist($donation);
            $entityManager->flush();

            $this->addFlash('success', 'Merci pour votre don ! +'.LoyaltyService::POINTS_DONATION.' points fidelite.');

            return $this->redirectToRoute('app_donation_my', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('donation/new.html.twig', [
            'donation' => $donation,
            'form' => $form,
        ]);
    }

    #[Route('/admin', name: 'app_donation_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
<<<<<<< HEAD
    public function index(Request $request, DonationRepository $donationRepository): Response
=======
>>>>>>> c4d1c44b0746a7387dc28bd3111400a167bda2d9
    {
        $search = $request->query->getString('q');
        $sort = $request->query->getString('sort', 'dateDon');
        $direction = $request->query->getString('direction', 'DESC');

        return $this->render('donation/index.html.twig', [
<<<<<<< HEAD
            'donations' => $donationRepository->findBySearchAndSort($search, $sort, $direction),
            'search' => $search,
            'sort' => $sort,
            'direction' => strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC',
=======
>>>>>>> c4d1c44b0746a7387dc28bd3111400a167bda2d9
        ]);
    }
}
