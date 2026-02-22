<?php

namespace App\Controller;

use App\Entity\Charity;
use App\Entity\Donation;
use App\Entity\User;
use App\Form\DonationType;
use App\Repository\CharityRepository;
use App\Repository\DonationRepository;
use App\Repository\TypeDonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\EmailService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/donation')]
class DonationController extends AbstractController
{
    #[Route('/test-email', name: 'app_test_email', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function testEmail(Request $request, EmailService $emailService): Response
    {
        if (!$this->isCsrfTokenValid('donation_test_email', (string) $request->request->get('_token'))) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

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
    public function new(Request $request, EntityManagerInterface $entityManager, CharityRepository $charityRepository, SluggerInterface $slugger): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur invalide.');
        }

        $donation = new Donation();
        $charityId = (int) $request->query->get('charity', 0);
        if ($charityId > 0) {
            $charity = $charityRepository->find($charityId);
            if ($charity instanceof Charity && $charity->getStatus() === Charity::STATUS_ACTIVE) {
                if ($charity->getCreatedBy() === $user) {
                    $this->addFlash('error', 'Vous ne pouvez pas faire un don à votre propre cause.');
                } else {
                    $donation->setCharity($charity);
                }
            }
        }

        $form = $this->createForm(DonationType::class, $donation, ['donor' => $user]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($donation->getCharity()?->getCreatedBy() === $user) {
                $this->addFlash('error', 'Vous ne pouvez pas faire un don à votre propre cause.');

                return $this->render('donation/new.html.twig', [
                    'donation' => $donation,
                    'form' => $form,
                ]);
            }

            $photoFile = $form->get('photoFile')->getData();
            if ($photoFile instanceof UploadedFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = (string) $slugger->slug($originalFilename);
                $extension = $photoFile->guessExtension() ?: 'bin';
                $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $extension;

                $targetDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/donations';
                if (!is_dir($targetDirectory)) {
                    mkdir($targetDirectory, 0775, true);
                }

                $photoFile->move($targetDirectory, $newFilename);
                $donation->setPhoto('uploads/donations/' . $newFilename);
            }

            try {
                $donation->setDonateur($user);
                $entityManager->persist($donation);
                $entityManager->flush();
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Échec de l\'enregistrement du don. Veuillez réessayer.');

                return $this->render('donation/new.html.twig', [
                    'donation' => $donation,
                    'form' => $form,
                ]);
            }

            $this->addFlash('sweet_success', 'Don ajouté avec succès.');

            return $this->redirectToRoute('app_donation_my', [], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Le formulaire contient des erreurs. Vérifiez la cause, le type et les champs obligatoires.');
        }

        return $this->render('donation/new.html.twig', [
            'donation' => $donation,
            'form' => $form,
        ]);
    }

    #[Route('/cause/{id}/comment', name: 'app_donation_comment', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function commentDonation(
        Request $request,
        Charity $charity,
        EntityManagerInterface $entityManager,
        TypeDonRepository $typeDonRepository,
        SluggerInterface $slugger
    ): Response {
        $page = max(1, (int) $request->request->get('page', 1));
        $redirectUrl = $this->generateUrl('app_charity_index', ['page' => $page]) . '#cause-' . $charity->getId();

        if (!$this->isCsrfTokenValid('comment_donation_' . $charity->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirect($redirectUrl);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez être connecté pour faire un don.');

            return $this->redirectToRoute('login');
        }

        if ($charity->getStatus() !== Charity::STATUS_ACTIVE) {
            $this->addFlash('error', 'Cette cause n\'accepte pas de dons pour le moment.');

            return $this->redirect($redirectUrl);
        }

        if ($charity->getCreatedBy() === $user) {
            $this->addFlash('error', 'Vous ne pouvez pas faire un don à votre propre cause.');

            return $this->redirect($redirectUrl);
        }

        $description = trim((string) $request->request->get('comment', ''));
        if ($description === '') {
            $this->addFlash('error', 'Le commentaire du don est obligatoire.');

            return $this->redirect($redirectUrl);
        }

        $amountInput = trim((string) $request->request->get('amount', ''));
        $amount = null;
        if ($amountInput !== '') {
            if (!is_numeric($amountInput)) {
                $this->addFlash('error', 'Le montant doit être un nombre valide.');

                return $this->redirect($redirectUrl);
            }

            $amount = (float) $amountInput;
            if ($amount < 0) {
                $this->addFlash('error', 'Le montant doit être positif ou nul.');

                return $this->redirect($redirectUrl);
            }
        }

        $type = $typeDonRepository->findDefaultForCommentDonation();
        if ($type === null) {
            $this->addFlash('error', 'Aucun type de don n\'est configuré.');

            return $this->redirect($redirectUrl);
        }

        $donation = new Donation();
        $donation
            ->setCharity($charity)
            ->setType($type)
            ->setDonateur($user)
            ->setDescription($description)
            ->setAmount($amount)
            ->setIsAnonymous($request->request->has('is_anonymous'))
            ->setDateDon(new \DateTimeImmutable());

        $photoFile = $request->files->get('photo_file');
        if ($photoFile instanceof UploadedFile) {
            $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = (string) $slugger->slug($originalFilename);
            $extension = $photoFile->guessExtension() ?: 'bin';
            $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $extension;

            $targetDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/donations';
            if (!is_dir($targetDirectory)) {
                mkdir($targetDirectory, 0775, true);
            }

            $photoFile->move($targetDirectory, $newFilename);
            $donation->setPhoto('uploads/donations/' . $newFilename);
        }

        try {
            $entityManager->persist($donation);
            $entityManager->flush();
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Échec de l\'enregistrement du don. Veuillez réessayer.');

            return $this->redirect($redirectUrl);
        }

        $this->addFlash('sweet_success', 'Don ajouté avec succès.');

        return $this->redirect($redirectUrl);
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
