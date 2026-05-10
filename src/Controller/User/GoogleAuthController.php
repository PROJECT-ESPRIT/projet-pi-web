<?php

namespace App\Controller\User;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleAuthController extends AbstractController
{
    private const GOOGLE_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const GOOGLE_USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';
    private const MISSING_CONFIG_MESSAGE = 'Connexion Google indisponible: configuration manquante.';
    private const STATE_TTL_SECONDS = 600;
    private readonly string $googleOauthClientId;
    private readonly string $googleOauthClientSecret;
    private readonly string $googleOauthRedirectUri;
    private readonly string $appSecret;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly Security $security,
        ?string $googleOauthClientId,
        ?string $googleOauthClientSecret,
        ?string $googleOauthRedirectUri,
        ?string $appSecret,
    ) {
        $this->googleOauthClientId = $googleOauthClientId ?? '';
        $this->googleOauthClientSecret = $googleOauthClientSecret ?? '';
        $this->googleOauthRedirectUri = trim($googleOauthRedirectUri ?? '');
        $this->appSecret = $appSecret ?? '';
    }

    #[Route('/connect/google', name: 'connect_google_start')]
    public function connect(Request $request): RedirectResponse
    {
        if (!$this->googleOauthClientId || !$this->googleOauthClientSecret) {
            $this->addFlash('danger', self::MISSING_CONFIG_MESSAGE);
            return $this->redirectToRoute('login');
        }

        $redirectUri = $this->googleOauthRedirectUri !== ''
            ? $this->googleOauthRedirectUri
            : $this->generateUrl('connect_google_check', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $state = $this->buildState($redirectUri);

        $params = http_build_query([
            'client_id' => $this->googleOauthClientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return $this->redirect(self::GOOGLE_AUTH_URL . '?' . $params);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function check(Request $request): Response
    {
        if (!$this->googleOauthClientId || !$this->googleOauthClientSecret) {
            $this->addFlash('danger', self::MISSING_CONFIG_MESSAGE);
            return $this->redirectToRoute('login');
        }

        $receivedState = (string) $request->query->get('state', '');
        $code = (string) $request->query->get('code', '');
        $error = (string) $request->query->get('error', '');

        if ($error !== '') {
            $message = 'Connexion Google annulee.';
            if ($error === 'invalid_request') {
                $message = 'Configuration Google invalide: redirect URI non autorisee.';
            }
            $this->addFlash('warning', $message);
            return $this->redirectToRoute('login');
        }

        $redirectUri = $this->extractRedirectUriFromState($receivedState);
        if ($redirectUri === null || $code === '') {
            $this->addFlash('danger', 'Echec de la connexion Google: requete invalide.');
            return $this->redirectToRoute('login');
        }

        try {
            $tokenResponse = $this->httpClient->request('POST', self::GOOGLE_TOKEN_URL, [
                'body' => [
                    'code' => $code,
                    'client_id' => $this->googleOauthClientId,
                    'client_secret' => $this->googleOauthClientSecret,
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                ],
            ])->toArray();

            $accessToken = (string) ($tokenResponse['access_token'] ?? '');
            if ($accessToken === '') {
                throw new \RuntimeException('Missing access token from Google.');
            }

            $googleUser = $this->httpClient->request('GET', self::GOOGLE_USERINFO_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ])->toArray();

            $email = strtolower((string) ($googleUser['email'] ?? ''));
            $emailVerified = (bool) ($googleUser['email_verified'] ?? false);
            if ($email === '' || !$emailVerified) {
                throw new \RuntimeException('Google account email is missing or not verified.');
            }

            $user = $this->userRepository->findOneBy(['email' => $email]);
            if (!$user) {
                $user = new User();
                $user->setEmail($email);
                $user->setPrenom((string) ($googleUser['given_name'] ?? 'Utilisateur'));
                $user->setNom((string) ($googleUser['family_name'] ?? 'Google'));
                $user->setRoles(['ROLE_PARTICIPANT']);
                $user->setStatus(User::STATUS_APPROVED);
                $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
                $user->setEmailVerificationToken(null);
                $user->setEmailVerificationSentAt(null);
                $user->setProfileImageUrl(isset($googleUser['picture']) ? (string) $googleUser['picture'] : null);

                $this->entityManager->persist($user);
                $this->entityManager->flush();
            } else {
                if (\in_array($user->getStatus(), [User::STATUS_REJECTED, User::STATUS_SUSPENDED], true)) {
                    $this->addFlash('danger', 'Votre compte est indisponible. Contactez l administration.');
                    return $this->redirectToRoute('login');
                }

                if ($user->getStatus() === User::STATUS_EMAIL_PENDING) {
                    $user->setStatus(User::STATUS_APPROVED);
                    $user->setEmailVerificationToken(null);
                    $user->setEmailVerificationSentAt(null);
                }

                if (!$user->getProfileImageUrl() && isset($googleUser['picture'])) {
                    $user->setProfileImageUrl((string) $googleUser['picture']);
                }

                $this->entityManager->flush();
            }

            $this->security->login($user, firewallName: 'main');

            return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'admin_stats' : 'home');
        } catch (\Throwable $e) {
            $message = 'Echec de la connexion Google. Reessayez.';

            if ($e instanceof HttpExceptionInterface) {
                $body = $e->getResponse()->getContent(false);

                if (str_contains($body, 'invalid_client')) {
                    $message = 'Configuration Google invalide: verifiez Client ID et Client Secret.';
                } elseif (str_contains($body, 'invalid_grant')) {
                    $message = 'Code Google invalide/expire. Relancez la connexion.';
                }
            }

            $this->addFlash('danger', $message);
            return $this->redirectToRoute('login');
        }
    }

    private function buildState(string $redirectUri): string
    {
        $payload = json_encode([
            'n' => bin2hex(random_bytes(12)),
            'ts' => time(),
            'r' => $redirectUri,
        ], JSON_THROW_ON_ERROR);
        $payloadEncoded = self::base64UrlEncode($payload);
        $signature = hash_hmac('sha256', $payloadEncoded, $this->appSecret);

        return $payloadEncoded . '.' . $signature;
    }

    private function extractRedirectUriFromState(string $state): ?string
    {
        if ($this->appSecret === '' || $state === '' || !str_contains($state, '.')) {
            return null;
        }

        [$payloadEncoded, $signature] = explode('.', $state, 2);
        $expectedSignature = hash_hmac('sha256', $payloadEncoded, $this->appSecret);
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payload = self::base64UrlDecode($payloadEncoded);
        if ($payload === false) {
            return null;
        }

        try {
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $issuedAt = (int) ($decoded['ts'] ?? 0);
        $redirectUri = (string) ($decoded['r'] ?? '');
        if ($issuedAt <= 0 || $redirectUri === '') {
            return null;
        }

        if ((time() - $issuedAt) > self::STATE_TTL_SECONDS) {
            return null;
        }

        return $redirectUri;
    }

    private static function base64UrlEncode(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $input): string|false
    {
        $padding = strlen($input) % 4;
        if ($padding > 0) {
            $input .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($input, '-_', '+/'), true);
    }
}
