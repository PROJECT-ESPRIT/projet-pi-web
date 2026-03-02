<?php

namespace App\Controller\Admin;

use App\Entity\Charity;
use App\Repository\CharityRepository;
use App\Repository\DonationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/charities')]
#[IsGranted('ROLE_ADMIN')]
class CharityController extends AbstractController
{
    #[Route('/', name: 'app_admin_charity_index', methods: ['GET'])]
    public function index(CharityRepository $charityRepository): Response
    {
        $rows = $charityRepository->findAllWithDonationCounts(true);

        $charityStats = array_map(static function (array $row): array {
            $charity = $row['charity'];
            $count = $row['donationsCount'];
            $amount = $row['donationsAmount'];
            $goal = $charity->getGoalAmount();
            $progressPercent = $goal ? round(($amount / $goal) * 100, 1) : 0;

            return [
                'charity' => $charity,
                'donationsCount' => $count,
                'donationsAmount' => $amount,
                'progressPercent' => $progressPercent,
                'hasGoal' => $goal !== null,
            ];
        }, $rows);

        return $this->render('admin/charity/index.html.twig', [
            'charityStats' => $charityStats,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_charity_show', methods: ['GET'])]
    public function show(Charity $charity, DonationRepository $donationRepository): Response
    {
        $donations = $donationRepository->findBy(['charity' => $charity], ['dateDon' => 'DESC']);
        $count = count($donations);
        $amount = $donationRepository->sumAmountForCharity($charity, true);
        $goal = $charity->getGoalAmount();

        return $this->render('admin/charity/show.html.twig', [
            'charity' => $charity,
            'donations' => $donations,
            'donationsCount' => $count,
            'donationsAmount' => $amount,
            'progressPercent' => $goal ? round(($amount / $goal) * 100, 1) : 0,
            'hasGoal' => $goal !== null,
        ]);
    }
}
