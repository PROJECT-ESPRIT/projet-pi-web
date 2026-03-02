<?php

namespace App\Controller;

use App\Entity\Charity;
use App\Entity\Donation;
use App\Entity\TypeDon;
use App\Entity\User;
use App\Form\CharityType;
use App\Repository\CharityRepository;
use App\Repository\DonationRepository;
use App\Repository\TypeDonRepository;
use App\Service\DonationImageValidator;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/charities')]
class CharityController extends AbstractController
{
    #[Route('', name: 'app_charity_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        CharityRepository $charityRepository,
        DonationRepository $donationRepository,
        TypeDonRepository $typeDonRepository,
        EntityManagerInterface $entityManager,
        DonationImageValidator $donationImageValidator,
        StripeService $stripeService,
        UrlGeneratorInterface $urlGenerator
    ): Response
    {
        [$donationErrors, $donationOld, $redirect] = $this->processDonationSubmission(
            $request,
            $charityRepository,
            $typeDonRepository,
            $entityManager,
            $donationImageValidator,
            $stripeService,
            $urlGenerator
        );

        if ($redirect instanceof Response) {
            return $redirect;
        }

        $search = $request->query->getString('q');
        $sort = $request->query->getString('sort', 'name');
        $direction = strtoupper($request->query->getString('direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $rows = $charityRepository->findAllWithDonationCounts(false, $search, $sort, $direction);

        $charityStats = array_map(static function (array $row): array {
            $charity = $row['charity'];
            $count = $row['donationsCount'];
            $amount = $row['donationsAmount'];
            $goal = $charity->getGoalAmount();

            return [
                'charity' => $charity,
                'donationsCount' => $count,
                'donationsAmount' => $amount,
                'progressPercent' => $goal ? round(($amount / $goal) * 100, 1) : 0,
                'hasGoal' => $goal !== null,
            ];
        }, $rows);

        $charityIds = array_values(array_filter(array_map(
            static fn (array $row): ?int => $row['charity']->getId(),
            $rows
        )));
        $recentDonations = $donationRepository->findRecentByCharityIds($charityIds, 6);

        return $this->render('charity/index.html.twig', [
            'charityStats' => $charityStats,
            'typeDons' => $this->getAllowedTypeDons($typeDonRepository),
            'donationErrors' => $donationErrors,
            'donationOld' => $donationOld,
            'recentDonations' => $recentDonations,
            'moneyTypeId' => $this->getMoneyTypeId(),
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'ai_validation_result' => $this->consumeAiResultFlash($request),
        ]);
    }

    #[Route('/mine', name: 'app_charity_mine', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function mine(Request $request, CharityRepository $charityRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $search = $request->query->getString('q');
        $sort = $request->query->getString('sort', 'name');
        $direction = strtoupper($request->query->getString('direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $rows = $charityRepository->findOwnedWithDonationCounts($user, $search, $sort, $direction);

        $charityStats = array_map(static function (array $row): array {
            $charity = $row['charity'];
            $count = $row['donationsCount'];
            $amount = $row['donationsAmount'];
            $goal = $charity->getGoalAmount();

            return [
                'charity' => $charity,
                'donationsCount' => $count,
                'donationsAmount' => $amount,
                'progressPercent' => $goal ? round(($amount / $goal) * 100, 1) : 0,
                'hasGoal' => $goal !== null,
            ];
        }, $rows);

        return $this->render('charity/mine.html.twig', [
            'charityStats' => $charityStats,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    #[Route('/new', name: 'app_charity_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $charity = new Charity();
        $charity->setOwner($user);

        $form = $this->createForm(CharityType::class, $charity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleCharityImageUpload($form->get('imageFile')->getData(), $charity);
            $entityManager->persist($charity);
            $entityManager->flush();

            $this->addFlash('success', 'Votre cause a été créée.');

            return $this->redirectToRoute('app_charity_mine');
        }

        return $this->render('charity/new.html.twig', [
            'charity' => $charity,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_charity_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, Charity $charity, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessOwner($charity);

        $form = $this->createForm(CharityType::class, $charity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleCharityImageUpload($form->get('imageFile')->getData(), $charity);
            $entityManager->flush();

            $this->addFlash('success', 'Votre cause a été mise à jour.');

            return $this->redirectToRoute('app_charity_mine');
        }

        return $this->render('charity/edit.html.twig', [
            'charity' => $charity,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_charity_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, Charity $charity, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessOwner($charity);

        if (!$this->isCsrfTokenValid('delete-charity-' . $charity->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_charity_mine');
        }

        $charity->setIsHidden(true);
        $entityManager->flush();

        $this->addFlash('success', 'Votre cause a été masquée pour les utilisateurs.');

        return $this->redirectToRoute('app_charity_mine');
    }

    #[Route('/{id}', name: 'app_charity_show', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function show(
        Request $request,
        Charity $charity,
        CharityRepository $charityRepository,
        DonationRepository $donationRepository,
        TypeDonRepository $typeDonRepository,
        EntityManagerInterface $entityManager,
        DonationImageValidator $donationImageValidator,
        StripeService $stripeService,
        UrlGeneratorInterface $urlGenerator
    ): Response
    {
        if ($charity->isHidden() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_charity_index');
        }

        [$donationErrors, $donationOld, $redirect] = $this->processDonationSubmission(
            $request,
            $charityRepository,
            $typeDonRepository,
            $entityManager,
            $donationImageValidator,
            $stripeService,
            $urlGenerator,
            $charity
        );

        if ($redirect instanceof Response) {
            return $redirect;
        }

        $includeHidden = $this->isGranted('ROLE_ADMIN');
        $donationsCount = $donationRepository->countForCharity($charity, $includeHidden);
        $donationsAmount = $donationRepository->sumAmountForCharity($charity, $includeHidden);
        $goal = $charity->getGoalAmount();

        return $this->render('charity/show.html.twig', [
            'charity' => $charity,
            'recentDonations' => $donationRepository->findByCharity($charity, 10, $includeHidden),
            'donationsCount' => $donationsCount,
            'donationsAmount' => $donationsAmount,
            'progressPercent' => $goal ? round(($donationsAmount / $goal) * 100, 1) : 0,
            'hasGoal' => $goal !== null,
            'typeDons' => $this->getAllowedTypeDons($typeDonRepository),
            'donationErrors' => $donationErrors,
            'donationOld' => $donationOld,
            'moneyTypeId' => $this->getMoneyTypeId(),
            'ai_validation_result' => $this->consumeAiResultFlash($request),
        ]);
    }

    #[Route('/{id}/donate', name: 'app_charity_donate', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function donate(
        Charity $charity,
        Request $request,
        TypeDonRepository $typeDonRepository,
        EntityManagerInterface $entityManager
    ): Response {
        return $this->redirectToRoute('app_charity_index', ['_fragment' => 'charity-' . $charity->getId()]);
    }

    /**
     * @return array{0: array, 1: array, 2: ?Response}
     */
    private function processDonationSubmission(
        Request $request,
        CharityRepository $charityRepository,
        TypeDonRepository $typeDonRepository,
        EntityManagerInterface $entityManager,
        DonationImageValidator $donationImageValidator,
        StripeService $stripeService,
        UrlGeneratorInterface $urlGenerator,
        ?Charity $contextCharity = null,
    ): array {
        $donationErrors = [];
        $donationOld = [];
        $redirect = null;

        if (!$request->isMethod('POST')) {
            return [$donationErrors, $donationOld, $redirect];
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return [$donationErrors, $donationOld, $this->redirectToRoute('login')];
        }

        $charityId = $contextCharity?->getId() ?? (int) $request->request->get('charity_id');
        $charity = $contextCharity ?? $charityRepository->find($charityId);

        if (!$charity) {
            if ($charityId > 0) {
                $donationErrors[$charityId]['general'] = 'Charity introuvable.';
            }
            return [$donationErrors, $donationOld, $redirect];
        }

        $charityId = $charity->getId();
        if (!$this->isCsrfTokenValid('donate-charity-' . $charityId, (string) $request->request->get('_token'))) {
            $donationErrors[$charityId]['general'] = 'Action non autorisée.';
        }
        if ($charity->isHidden()) {
            $donationErrors[$charityId]['general'] = 'Cette charity n’est plus disponible.';
        }
        if ($charity->getOwner() && $charity->getOwner()->getId() === $user->getId()) {
            $donationErrors[$charityId]['general'] = 'Vous ne pouvez pas donner à votre propre charity.';
        }

        $typeId = (int) $request->request->get('type_id');
        $type = $typeDonRepository->find($typeId);
        if (!$type) {
            $donationErrors[$charityId]['type_id'] = 'Veuillez choisir un type de don.';
        }

        $description = $request->request->getString('description');
        $isAnonymous = $request->request->getBoolean('is_anonymous');
        $amount = (int) $request->request->get('amount');
        $isMoney = $type && ($this->isMoneyLabel($type->getLibelle()) || $this->isMoneyTypeId($type->getId()));
        $expectedLabel = $type ? $this->mapDonationTypeToLabel($type->getLibelle()) : '';

        /** @var UploadedFile|null $imageFile */
        $imageFile = $request->files->get('donation_image');
        if ($type && !$isMoney && !$imageFile instanceof UploadedFile) {
            $donationErrors[$charityId]['donation_image'] = 'Veuillez ajouter une image du don.';
        }

        if ($isMoney && $amount <= 0) {
            $donationErrors[$charityId]['amount'] = 'Veuillez saisir un montant valide.';
        }

        $donationOld[$charityId] = [
            'type_id' => $typeId,
            'description' => $description,
            'amount' => $amount,
            'is_anonymous' => $isAnonymous,
        ];

        if (isset($donationErrors[$charityId])) {
            return [$donationErrors, $donationOld, $redirect];
        }

        if ($isMoney) {
            try {
                $pendingId = bin2hex(random_bytes(8));
                $successUrl = $this->buildAbsoluteUrl(
                    $urlGenerator,
                    'app_donation_payment_success',
                    ['pending_id' => $pendingId]
                );
                $cancelUrl = $this->buildAbsoluteUrl(
                    $urlGenerator,
                    'app_donation_payment_cancel',
                    ['pending_id' => $pendingId]
                );
                $session = $stripeService->createCheckoutSessionForDonation($charity, $user, $amount, $successUrl, $cancelUrl);

                $pending = $request->getSession()->get('pending_donations', []);
                $pending[$pendingId] = [
                    'charity_id' => $charity->getId(),
                    'type_id' => $type->getId(),
                    'description' => $description ?: null,
                    'is_anonymous' => $isAnonymous,
                    'amount' => $amount,
                    'image_path' => null,
                    'user_id' => $user->getId(),
                    'stripe_session_id' => $session['id'],
                    'return_route' => $contextCharity ? 'app_charity_show' : 'app_charity_index',
                    'return_params' => $contextCharity
                        ? ['id' => $charity->getId()]
                        : ['_fragment' => 'charity-' . $charity->getId()],
                ];
                $request->getSession()->set('pending_donations', $pending);

                $redirect = $this->redirect($session['url']);
                return [$donationErrors, $donationOld, $redirect];
            } catch (\Throwable $e) {
                $donationErrors[$charityId]['general'] = 'Impossible d\'ouvrir la page de paiement. Vérifiez la configuration Stripe (clé secrète sk_ dans .env).';
                return [$donationErrors, $donationOld, $redirect];
            }
        }

        $donation = new Donation();
        $donation->setCharity($charity);
        $donation->setType($type);
        $donation->setDescription($description ?: null);
        $donation->setIsAnonymous($isAnonymous);
        $donation->setAmount($isMoney ? max(0, $amount) : 0);
        $donation->setDonateur($user);

        if (!$this->handleDonationImageUpload($imageFile, $donation)) {
            $donationErrors[$charityId]['donation_image'] = 'Impossible d\'enregistrer l\'image du don.';
            return [$donationErrors, $donationOld, $redirect];
        }

        if (!$isMoney) {
            $validation = $donationImageValidator->validate($donation->getImagePath(), $expectedLabel);
            if (empty($validation['ok'])) {
                $this->cleanupDonationFile($donation->getImagePath());
                $detail = $validation['message'] ?? 'L\'image ne correspond pas au type de don.';
                if (!empty($validation['label'])) {
                    $conf = isset($validation['confidence']) ? number_format((float) $validation['confidence'], 2) : null;
                    $detail .= ' (IA: ' . $validation['label'] . ($conf ? ' @ ' . $conf : '') . ')';
                }
                $donationErrors[$charityId]['donation_image'] = $detail;
                $this->addFlash('danger', $detail);
                $this->addFlash('ai_validation_result', json_encode([
                    'ok' => false,
                    'message' => $detail,
                ]));
                return [$donationErrors, $donationOld, $redirect];
            }
            if (!empty($validation['skipped'])) {
                $this->addFlash('info', 'Validation IA ignorée (modèle manquant).');
            }
            $this->addFlash('ai_validation_result', json_encode([
                'ok' => true,
                'message' => $validation['message'] ?? 'Image validée.',
                'skipped' => !empty($validation['skipped']),
            ]));
        }

        $entityManager->persist($donation);
        $entityManager->flush();

        $this->addFlash('success', 'Merci pour votre don à ' . $charity->getName() . ' !');
        $redirect = $this->redirectToRoute(
            $contextCharity ? 'app_charity_show' : 'app_charity_index',
            $contextCharity ? ['id' => $charity->getId()] : ['_fragment' => 'charity-' . $charity->getId()]
        );

        return [$donationErrors, $donationOld, $redirect];
    }

    private function handleCharityImageUpload(?UploadedFile $file, Charity $charity): void
    {
        if (!$file instanceof UploadedFile) {
            return;
        }

        $root = $this->getParameter('kernel.project_dir');
        $uploadDir = $root . '/public/uploads/charities';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $safeExt = $file->guessExtension() ?: 'bin';
        $fileName = 'charity_' . bin2hex(random_bytes(8)) . '.' . $safeExt;
        $file->move($uploadDir, $fileName);
        $charity->setImagePath('/uploads/charities/' . $fileName);
    }

    private function handleDonationImageUpload(?UploadedFile $file, Donation $donation): bool
    {
        if (!$file instanceof UploadedFile) {
            return false;
        }

        $root = $this->getParameter('kernel.project_dir');
        $uploadDir = $root . '/public/uploads/donations';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        try {
            $safeExt = $file->guessExtension() ?: 'bin';
            $fileName = 'donation_' . bin2hex(random_bytes(8)) . '.' . $safeExt;
            $file->move($uploadDir, $fileName);
            $donation->setImagePath('/uploads/donations/' . $fileName);
        } catch (\Throwable $e) {
            return false;
        }

        return true;
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

    private function isMoneyLabel(?string $label): bool
    {
        if (!$label) {
            return false;
        }
        $value = mb_strtolower($label);
        foreach (['argent', 'money', 'dinar', 'dt', 'tnd', 'euro', 'eur', '€'] as $keyword) {
            if (str_contains($value, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function mapDonationTypeToLabel(?string $label): string
    {
        if (!$label) {
            return '';
        }
        $value = mb_strtolower($label);
        $mapFile = $this->getParameter('kernel.project_dir') . '/config/donation_label_map.json';
        if (is_file($mapFile)) {
            $raw = file_get_contents($mapFile);
            $map = $raw ? json_decode($raw, true) : null;
            if (is_array($map)) {
                foreach ($map as $target => $keywords) {
                    if (!is_array($keywords)) {
                        continue;
                    }
                    foreach ($keywords as $keyword) {
                        if (!is_string($keyword) || $keyword === '') {
                            continue;
                        }
                        if (str_contains($value, mb_strtolower($keyword))) {
                            return (string) $target;
                        }
                    }
                }
            }
        }
        return $value;
    }

    private function isMoneyTypeId(?int $id): bool
    {
        if (!$id) {
            return false;
        }
        $configured = $this->getMoneyTypeId();
        return $configured > 0 && $id === $configured;
    }

    private function getMoneyTypeId(): int
    {
        $value = getenv('MONEY_TYPE_ID');
        if ($value === false || $value === null || $value === '') {
            return 0;
        }
        return (int) $value;
    }

    private function buildAbsoluteUrl(
        UrlGeneratorInterface $urlGenerator,
        string $route,
        array $params = []
    ): string {
        $base = getenv('APP_URL');
        if ($base !== false && $base !== null && $base !== '') {
            $path = $urlGenerator->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_PATH);
            return rtrim($base, '/') . $path;
        }

        return $urlGenerator->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function denyAccessUnlessOwner(Charity $charity): void
    {
        $user = $this->getUser();
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }
        if (!$user instanceof User || $charity->getOwner()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Accès refusé à cette cause.');
        }
    }

    private function consumeAiResultFlash(Request $request): ?array
    {
        $session = $request->getSession();
        if (!$session) {
            return null;
        }
        $messages = $session->getFlashBag()->get('ai_validation_result');
        if (empty($messages)) {
            return null;
        }
        $raw = $messages[0];
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<int, TypeDon>
     */
    private function getAllowedTypeDons(TypeDonRepository $typeDonRepository): array
    {
        $all = $typeDonRepository->findAll();
        $allowed = $this->loadAllowedTypeLabels();
        if ($allowed === []) {
            return $all;
        }
        $allowedLower = array_map(static fn (string $label) => mb_strtolower($label), $allowed);
        return array_values(array_filter($all, static function (TypeDon $type) use ($allowedLower): bool {
            $label = mb_strtolower((string) $type->getLibelle());
            return in_array($label, $allowedLower, true);
        }));
    }

    /**
     * @return string[]
     */
    private function loadAllowedTypeLabels(): array
    {
        $path = $this->getParameter('kernel.project_dir') . '/config/donation_types.json';
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        $data = $raw ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return [];
        }
        return array_values(array_filter($data, static fn ($v): bool => is_string($v) && $v !== ''));
    }
}
