<?php

namespace App\Controller\Admin;

use App\Entity\Charity;
use App\Repository\CharityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/charity')]
#[IsGranted('ROLE_ADMIN')]
class CharityAdminController extends AbstractController
{
    #[Route('/', name: 'app_admin_charity_index', methods: ['GET'])]
    public function index(Request $request, CharityRepository $charityRepository): Response
    {
        $status = strtoupper(trim((string) $request->query->get('status', 'ALL')));
        $allowedStatuses = ['ALL', Charity::STATUS_ACTIVE, Charity::STATUS_REJECTED];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'ALL';
        }

        $creator = trim((string) $request->query->get('creator', ''));

        $minDonationsInput = trim((string) $request->query->get('min_donations', ''));
        $minDonations = null;
        if ($minDonationsInput !== '') {
            $minDonations = max(0, (int) $minDonationsInput);
        }

        return $this->render('admin/charity/index.html.twig', [
            'rows' => $charityRepository->findForAdminFilters($status, $creator, $minDonations),
            'filters' => [
                'status' => $status,
                'creator' => $creator,
                'min_donations' => $minDonationsInput,
            ],
        ]);
    }
}
