<?php

namespace App\Controller;

use App\Entity\Charity;
use App\Entity\Donation;
use App\Entity\User;
use App\Form\DonationType;
use App\Repository\CharityRepository;
use App\Repository\DonationRepository;
use App\Repository\TypeDonRepository;
use App\Service\StripeCheckoutService;
use App\Service\YoloDonationImageValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        CharityRepository $charityRepository,
        SluggerInterface $slugger,
        YoloDonationImageValidator $imageValidator,
        StripeCheckoutService $stripeCheckoutService
    ): Response
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

            if ($this->isMoneyTypeLabel($donation->getType()?->getLibelle())) {
                $amount = $donation->getAmount();
                if ($amount === null || $amount <= 0) {
                    $this->addFlash('error', 'Pour un don money, le montant doit être supérieur à 0.');

                    return $this->render('donation/new.html.twig', [
                        'donation' => $donation,
                        'form' => $form,
                    ]);
                }

                $charity = $donation->getCharity();
                if (!$charity instanceof Charity || $charity->getId() === null || $donation->getType()?->getId() === null) {
                    $this->addFlash('error', 'Cause ou type de don invalide.');

                    return $this->render('donation/new.html.twig', [
                        'donation' => $donation,
                        'form' => $form,
                    ]);
                }

                $pending = [
                    'source' => 'donation_form',
                    'charity_id' => $charity->getId(),
                    'charity_title' => (string) $charity->getTitle(),
                    'type_id' => $donation->getType()->getId(),
                    'description' => (string) $donation->getDescription(),
                    'amount' => $amount,
                    'is_anonymous' => $donation->isAnonymous(),
                ];

                try {
                    return $this->createStripeCheckoutRedirect($request, $stripeCheckoutService, $pending);
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Paiement Stripe indisponible: ' . $e->getMessage());

                    return $this->render('donation/new.html.twig', [
                        'donation' => $donation,
                        'form' => $form,
                    ]);
                }
            }

            $photoFile = $form->get('photoFile')->getData();
            $validation = $imageValidator->validate(
                $photoFile instanceof UploadedFile ? $photoFile->getPathname() : null,
                $donation->getType()?->getLibelle()
            );
            if (!$validation['is_valid']) {
                if (($validation['service_error'] ?? false) === true) {
                    $this->addFlash('error', $validation['message']);
                } else {
                    $this->addFlash('sweet_warning', 'please send a valid picture');
                }

                return $this->render('donation/new.html.twig', [
                    'donation' => $donation,
                    'form' => $form,
                ]);
            }

            if ($photoFile instanceof UploadedFile) {
                try {
                    $donation->setPhoto($this->storeDonationPhoto($photoFile, $slugger));
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Échec de l\'upload de la photo. Veuillez réessayer.');

                    return $this->render('donation/new.html.twig', [
                        'donation' => $donation,
                        'form' => $form,
                    ]);
                }
            }

            try {
                $donation->setDonateur($user);
                $entityManager->persist($donation);
                $entityManager->flush();
            } catch (\Throwable $e) {
                $this->removeDonationPhoto($donation->getPhoto());
                $this->addFlash('error', 'Échec de l\'enregistrement du don. Veuillez réessayer.');

                return $this->render('donation/new.html.twig', [
                    'donation' => $donation,
                    'form' => $form,
                ]);
            }

            $this->addFlash('sweet_success', 'Donation successful, thank you for your contribution.');

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
        SluggerInterface $slugger,
        YoloDonationImageValidator $imageValidator,
        StripeCheckoutService $stripeCheckoutService
    ): Response {
        $page = max(1, (int) $request->request->get('page', 1));
        $redirectUrl = $this->generateUrl('app_charity_index', ['page' => $page]) . '#cause-' . $charity->getId();
        $redirectUrlOpenForm = $this->generateUrl('app_charity_index', [
            'page' => $page,
            'open_donate' => $charity->getId(),
        ]) . '#cause-' . $charity->getId();

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

        $typeId = (int) $request->request->get('type_id', 0);
        if ($typeId <= 0) {
            $this->addFlash('error', 'Le type de don est obligatoire.');

            return $this->redirect($redirectUrl);
        }

        $type = $typeDonRepository->find($typeId);
        if ($type === null) {
            $this->addFlash('error', 'Type de don invalide.');

            return $this->redirect($redirectUrl);
        }

        if ($this->isMoneyTypeLabel($type->getLibelle())) {
            if ($amount === null || $amount <= 0) {
                $this->addFlash('error', 'Pour un don money, le montant doit être supérieur à 0.');
                $this->addFlash('donation_comment_draft', [
                    'charity_id' => $charity->getId(),
                    'type_id' => $typeId,
                    'comment' => $description,
                    'amount' => $amountInput,
                    'is_anonymous' => $request->request->has('is_anonymous'),
                ]);

                return $this->redirect($redirectUrlOpenForm);
            }

            $pending = [
                'source' => 'charity_comment',
                'page' => $page,
                'charity_id' => $charity->getId(),
                'charity_title' => (string) $charity->getTitle(),
                'type_id' => $type->getId(),
                'description' => $description,
                'amount' => $amount,
                'is_anonymous' => $request->request->has('is_anonymous'),
            ];

            try {
                return $this->createStripeCheckoutRedirect($request, $stripeCheckoutService, $pending);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Paiement Stripe indisponible: ' . $e->getMessage());
                $this->addFlash('donation_comment_draft', [
                    'charity_id' => $charity->getId(),
                    'type_id' => $typeId,
                    'comment' => $description,
                    'amount' => $amountInput,
                    'is_anonymous' => $request->request->has('is_anonymous'),
                ]);

                return $this->redirect($redirectUrlOpenForm);
            }
        }

        $photoFile = $request->files->get('photo_file');
        $validation = $imageValidator->validate(
            $photoFile instanceof UploadedFile ? $photoFile->getPathname() : null,
            $type->getLibelle()
        );
        if (!$validation['is_valid']) {
            if (($validation['service_error'] ?? false) === true) {
                $this->addFlash('error', $validation['message']);

                return $this->redirect($redirectUrl);
            } else {
                $this->addFlash('sweet_warning', 'please send a valid picture');
                $this->addFlash('donation_comment_draft', [
                    'charity_id' => $charity->getId(),
                    'type_id' => $typeId,
                    'comment' => $description,
                    'amount' => $amountInput,
                    'is_anonymous' => $request->request->has('is_anonymous'),
                ]);

                return $this->redirect($redirectUrlOpenForm);
            }
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

        if ($photoFile instanceof UploadedFile) {
            try {
                $donation->setPhoto($this->storeDonationPhoto($photoFile, $slugger));
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Échec de l\'upload de la photo. Veuillez réessayer.');

                return $this->redirect($redirectUrl);
            }
        }

        try {
            $entityManager->persist($donation);
            $entityManager->flush();
        } catch (\Throwable $e) {
            $this->removeDonationPhoto($donation->getPhoto());
            $this->addFlash('error', 'Échec de l\'enregistrement du don. Veuillez réessayer.');

            return $this->redirect($redirectUrl);
        }

        $this->addFlash('sweet_success', 'Donation successful, thank you for your contribution.');

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

    #[Route('/payment/stripe/success/{token}', name: 'app_donation_stripe_success', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function stripeSuccess(
        string $token,
        Request $request,
        StripeCheckoutService $stripeCheckoutService,
        CharityRepository $charityRepository,
        TypeDonRepository $typeDonRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Utilisateur invalide.');

            return $this->redirectToRoute('login');
        }

        $pending = $this->getPendingStripeDonation($request, $token);
        if (!is_array($pending)) {
            $this->addFlash('error', 'Aucune tentative de paiement en attente.');

            return $this->redirectToRoute('app_donation_new');
        }

        $sessionId = trim((string) $request->query->get('session_id', ''));
        if ($sessionId === '') {
            $this->addFlash('error', 'session_id Stripe manquant.');

            return $this->redirect($this->buildReturnUrlForPending($pending, false));
        }

        try {
            $checkout = $stripeCheckoutService->getCheckoutSession($sessionId);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Vérification Stripe impossible: ' . $e->getMessage());

            return $this->redirect($this->buildReturnUrlForPending($pending, false));
        }

        $paymentStatus = (string) ($checkout['payment_status'] ?? '');
        $status = (string) ($checkout['status'] ?? '');
        $metadataToken = trim((string) ($checkout['metadata']['payment_token'] ?? ''));
        $isTokenValid = $metadataToken === '' || hash_equals($token, $metadataToken);
        if (!$isTokenValid || $paymentStatus !== 'paid' || $status !== 'complete') {
            $this->addFlash('error', 'Paiement non validé.');

            return $this->redirect($this->buildReturnUrlForPending($pending, false));
        }

        try {
            $charity = $charityRepository->find((int) ($pending['charity_id'] ?? 0));
            $type = $typeDonRepository->find((int) ($pending['type_id'] ?? 0));

            if (!$charity instanceof Charity || $charity->getId() === null || $type === null) {
                throw new \RuntimeException('Cause ou type de don introuvable.');
            }
            if ($charity->getStatus() !== Charity::STATUS_ACTIVE) {
                throw new \RuntimeException('Cette cause n\'accepte pas de dons pour le moment.');
            }
            if ($charity->getCreatedBy() === $user) {
                throw new \RuntimeException('Vous ne pouvez pas faire un don à votre propre cause.');
            }

            $donation = new Donation();
            $donation
                ->setCharity($charity)
                ->setType($type)
                ->setDonateur($user)
                ->setDescription((string) ($pending['description'] ?? ''))
                ->setAmount((float) ($pending['amount'] ?? 0))
                ->setIsAnonymous((bool) ($pending['is_anonymous'] ?? false))
                ->setDateDon(new \DateTimeImmutable());

            $entityManager->persist($donation);
            $entityManager->flush();
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Échec de l\'enregistrement du don après paiement: ' . $e->getMessage());

            return $this->redirect($this->buildReturnUrlForPending($pending, false));
        }

        $this->removePendingStripeDonation($request, $token);
        $this->addFlash('sweet_success', 'Donation successful, thank you for your contribution.');

        if (($pending['source'] ?? '') === 'charity_comment') {
            return $this->redirect($this->buildReturnUrlForPending($pending, false));
        }

        return $this->redirectToRoute('app_donation_my');
    }

    #[Route('/payment/stripe/cancel/{token}', name: 'app_donation_stripe_cancel', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function stripeCancel(string $token, Request $request): Response
    {
        $pending = $this->getPendingStripeDonation($request, $token);
        if (!is_array($pending)) {
            $this->addFlash('info', 'Paiement annulé.');

            return $this->redirectToRoute('app_donation_new');
        }

        $this->removePendingStripeDonation($request, $token);
        $this->addFlash('info', 'Paiement annulé.');

        if (($pending['source'] ?? '') === 'charity_comment') {
            $this->addFlash('donation_comment_draft', [
                'charity_id' => (int) ($pending['charity_id'] ?? 0),
                'type_id' => (int) ($pending['type_id'] ?? 0),
                'comment' => (string) ($pending['description'] ?? ''),
                'amount' => (string) ($pending['amount'] ?? ''),
                'is_anonymous' => (bool) ($pending['is_anonymous'] ?? false),
            ]);

            return $this->redirect($this->buildReturnUrlForPending($pending, true));
        }

        $charityId = (int) ($pending['charity_id'] ?? 0);
        if ($charityId > 0) {
            return $this->redirectToRoute('app_donation_new', ['charity' => $charityId]);
        }

        return $this->redirectToRoute('app_donation_new');
    }

    /**
     * @param array<string, mixed> $pending
     */
    private function createStripeCheckoutRedirect(
        Request $request,
        StripeCheckoutService $stripeCheckoutService,
        array $pending
    ): Response {
        $amount = (float) ($pending['amount'] ?? 0);
        $amountCents = (int) round($amount * 100);
        if ($amountCents <= 0) {
            throw new \RuntimeException('Montant de paiement invalide.');
        }

        $token = bin2hex(random_bytes(16));
        $pending['token'] = $token;
        $this->savePendingStripeDonation($request, $token, $pending);

        $successUrl = $this->generateUrl(
            'app_donation_stripe_success',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        ) . '?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $this->generateUrl(
            'app_donation_stripe_cancel',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $charityTitle = trim((string) ($pending['charity_title'] ?? ''));
        $description = $charityTitle !== ''
            ? sprintf('Donation money pour %s', $charityTitle)
            : 'Donation money';

        $checkout = $stripeCheckoutService->createCheckoutSession(
            $amountCents,
            $successUrl,
            $cancelUrl,
            $description,
            [
                'payment_token' => $token,
                'source' => (string) ($pending['source'] ?? 'donation_form'),
            ]
        );

        return $this->redirect($checkout['url'], Response::HTTP_SEE_OTHER);
    }

    /**
     * @param array<string, mixed> $pending
     */
    private function buildReturnUrlForPending(array $pending, bool $openForm): string
    {
        if (($pending['source'] ?? '') !== 'charity_comment') {
            $charityId = (int) ($pending['charity_id'] ?? 0);
            if ($charityId > 0) {
                return $this->generateUrl('app_donation_new', ['charity' => $charityId]);
            }

            return $this->generateUrl('app_donation_new');
        }

        $charityId = (int) ($pending['charity_id'] ?? 0);
        $page = max(1, (int) ($pending['page'] ?? 1));
        $params = ['page' => $page];
        if ($openForm && $charityId > 0) {
            $params['open_donate'] = $charityId;
        }

        $url = $this->generateUrl('app_charity_index', $params);
        if ($charityId > 0) {
            $url .= '#cause-' . $charityId;
        }

        return $url;
    }

    private function isMoneyTypeLabel(?string $label): bool
    {
        $normalized = $this->normalizeTypeLabel((string) $label);
        if ($normalized === '') {
            return false;
        }

        return str_contains($normalized, 'money')
            || str_contains($normalized, 'argent')
            || str_contains($normalized, 'cash');
    }

    private function normalizeTypeLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        if (is_string($transliterated) && $transliterated !== '') {
            $label = $transliterated;
        }

        $label = strtolower($label);

        return trim((string) preg_replace('/\s+/', ' ', $label));
    }

    /**
     * @param array<string, mixed> $pending
     */
    private function savePendingStripeDonation(Request $request, string $token, array $pending): void
    {
        $session = $request->getSession();
        $allPending = $session->get('donation_stripe_pending', []);
        if (!is_array($allPending)) {
            $allPending = [];
        }

        $allPending[$token] = $pending;
        $session->set('donation_stripe_pending', $allPending);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getPendingStripeDonation(Request $request, string $token): ?array
    {
        $session = $request->getSession();
        $allPending = $session->get('donation_stripe_pending', []);
        if (!is_array($allPending)) {
            return null;
        }

        $pending = $allPending[$token] ?? null;

        return is_array($pending) ? $pending : null;
    }

    private function removePendingStripeDonation(Request $request, string $token): void
    {
        $session = $request->getSession();
        $allPending = $session->get('donation_stripe_pending', []);
        if (!is_array($allPending)) {
            return;
        }

        unset($allPending[$token]);
        $session->set('donation_stripe_pending', $allPending);
    }

    private function storeDonationPhoto(UploadedFile $photoFile, SluggerInterface $slugger): string
    {
        $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = (string) $slugger->slug($originalFilename);
        $extension = $photoFile->guessExtension() ?: 'bin';
        $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $extension;

        $targetDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/donations';
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new \RuntimeException('Impossible de créer le dossier des photos de dons.');
        }

        $photoFile->move($targetDirectory, $newFilename);

        return 'uploads/donations/' . $newFilename;
    }

    private function removeDonationPhoto(?string $relativePhotoPath): void
    {
        if ($relativePhotoPath === null || trim($relativePhotoPath) === '') {
            return;
        }

        $absolutePhotoPath = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($relativePhotoPath, '/');
        if (is_file($absolutePhotoPath)) {
            @unlink($absolutePhotoPath);
        }
    }
}
