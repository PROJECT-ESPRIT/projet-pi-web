<?php

namespace App\Controller;

use App\Entity\Donation;
use App\Form\DonationType;
use App\Repository\DonationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/donation')]
class DonationController extends AbstractController
{
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
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $donation = new Donation();
        $form = $this->createForm(DonationType::class, $donation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $donation->setDonateur($this->getUser());
            $entityManager->persist($donation);
            $entityManager->flush();

            $this->addFlash('success', 'Merci pour votre don !');

            return $this->redirectToRoute('app_donation_my', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('donation/new.html.twig', [
            'donation' => $donation,
            'form' => $form,
        ]);
    }

    #[Route('/admin', name: 'app_donation_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request, DonationRepository $donationRepository): Response
    {
        $search = $request->query->getString('q');
        $sort = $request->query->getString('sort', 'dateDon');
        $direction = $request->query->getString('direction', 'DESC');

        return $this->render('donation/index.html.twig', [
            'donations' => $donationRepository->findBySearchAndSort($search, $sort, $direction),
            'search' => $search,
            'sort' => $sort,
            'direction' => strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC',
        ]);
    }
}
