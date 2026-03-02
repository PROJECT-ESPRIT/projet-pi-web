<?php

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OnboardingChatbotService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $aiProvider,
        private readonly string $openAiApiKey,
        private readonly string $openAiModel,
        private readonly string $anthropicApiKey,
        private readonly string $anthropicModel,
    ) {
    }

    /**
     * @param array<int, array{role?: mixed, content?: mixed}> $history
     *
     * @return array{
     *     reply: string,
     *     suggestedActions: array<int, array{label: string, path: string}>,
     *     provider: string
     * }
     */
    public function generateReply(User $user, string $message, array $history = []): array
    {
        $message = trim($message);
        if ($message == '') {
            throw new \InvalidArgumentException('Le message ne peut pas etre vide.');
        }

        $history = $this->normalizeHistory($history);
        $suggestedActions = $this->buildSuggestedActions($user);
        $systemPrompt = $this->buildSystemPrompt($user, $suggestedActions);

        $provider = strtolower(trim($this->aiProvider));
        if ($provider === '') {
            $provider = 'openai';
        }

        $reply = null;

        try {
            if ($provider === 'anthropic') {
                $reply = $this->callAnthropic($systemPrompt, $message, $history);
            } else {
                $provider = 'openai';
                $reply = $this->callOpenAi($systemPrompt, $message, $history);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Onboarding chatbot provider call failed.', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
        }

        if ($reply === null || trim($reply) === '') {
            $provider = 'fallback';
            $reply = $this->buildFallbackReply($user, $message, $suggestedActions);
        }

        return [
            'reply' => $reply,
            'suggestedActions' => $suggestedActions,
            'provider' => $provider,
        ];
    }

    /**
     * @param array<int, array{role?: mixed, content?: mixed}> $history
     *
     * @return array{
     *     reply: string,
     *     suggestedActions: array<int, array{label: string, path: string}>,
     *     provider: string
     * }
     */
    public function generateGuestReply(string $message, array $history = []): array
    {
        $message = trim($message);
        if ($message == '') {
            throw new \InvalidArgumentException('Le message ne peut pas etre vide.');
        }

        $history = $this->normalizeHistory($history);
        $suggestedActions = [
            ['label' => 'Se connecter', 'path' => '/login'],
            ['label' => 'Creer un compte', 'path' => '/register'],
            ['label' => 'Voir les evenements', 'path' => '/events/'],
            ['label' => 'Decouvrir la plateforme', 'path' => '/'],
        ];
        $systemPrompt = $this->buildGuestSystemPrompt();

        $provider = strtolower(trim($this->aiProvider));
        if ($provider === '') {
            $provider = 'openai';
        }

        $reply = null;

        try {
            if ($provider === 'anthropic') {
                $reply = $this->callAnthropic($systemPrompt, $message, $history);
            } else {
                $provider = 'openai';
                $reply = $this->callOpenAi($systemPrompt, $message, $history);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Guest onboarding chatbot provider call failed.', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
        }

        if ($reply === null || trim($reply) === '') {
            $provider = 'fallback';
            $reply = $this->buildGuestFallbackReply($message);
        }

        return [
            'reply' => $reply,
            'suggestedActions' => $suggestedActions,
            'provider' => $provider,
        ];
    }

    /**
     * @param array<int, array{role: string, content: string}> $history
     */
    private function callOpenAi(string $systemPrompt, string $message, array $history): ?string
    {
        if (trim($this->openAiApiKey) === '') {
            return null;
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($history as $item) {
            $messages[] = [
                'role' => $item['role'],
                'content' => $item['content'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openAiApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->openAiModel,
                'messages' => $messages,
                'temperature' => 0.4,
                'max_tokens' => 450,
            ],
            'timeout' => 20,
        ]);

        $data = $response->toArray(false);
        $content = $data['choices'][0]['message']['content'] ?? null;

        if (!is_string($content) || trim($content) === '') {
            return null;
        }

        return trim($content);
    }

    /**
     * @param array<int, array{role: string, content: string}> $history
     */
    private function callAnthropic(string $systemPrompt, string $message, array $history): ?string
    {
        if (trim($this->anthropicApiKey) === '') {
            return null;
        }

        $messages = [];
        foreach ($history as $item) {
            $messages[] = [
                'role' => $item['role'],
                'content' => $item['content'],
            ];
        }
        $messages[] = [
            'role' => 'user',
            'content' => $message,
        ];

        $response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $this->anthropicApiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
            'json' => [
                'model' => $this->anthropicModel,
                'max_tokens' => 450,
                'temperature' => 0.4,
                'system' => $systemPrompt,
                'messages' => $messages,
            ],
            'timeout' => 20,
        ]);

        $data = $response->toArray(false);
        $content = $data['content'][0]['text'] ?? null;

        if (!is_string($content) || trim($content) === '') {
            return null;
        }

        return trim($content);
    }

    /**
     * @param array<int, array{role?: mixed, content?: mixed}> $history
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function normalizeHistory(array $history): array
    {
        $normalized = [];

        foreach ($history as $item) {
            if (!is_array($item)) {
                continue;
            }

            $role = strtolower((string) ($item['role'] ?? ''));
            $content = trim((string) ($item['content'] ?? ''));

            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            if ($content === '') {
                continue;
            }

            $normalized[] = [
                'role' => $role,
                'content' => mb_substr($content, 0, 1200),
            ];
        }

        return array_slice($normalized, -8);
    }

    /**
     * @param array<int, array{label: string, path: string}> $actions
     */
    private function buildSystemPrompt(User $user, array $actions): string
    {
        $role = $this->mainRole($user);
        $status = $user->getStatus();
        $segment = $user->getSegment() ?? 'N/A';
        $fullName = trim($user->getPrenom() . ' ' . $user->getNom());

        $actionsLines = [];
        foreach ($actions as $action) {
            $actionsLines[] = '- ' . $action['label'] . ': ' . $action['path'];
        }

        return implode("\n", [
            'Tu es un assistant d onboarding pour une plateforme web evenementielle et communautaire.',
            'Objectif: guider le nouvel utilisateur vers ses premieres actions utiles.',
            'Regles:',
            '- Repondre en francais.',
            '- Reponse concise, concrete, orientee actions.',
            '- Si le statut n est pas APPROVED, expliquer clairement ce que cela implique.',
            '- Toujours proposer une prochaine etape immediate dans l application.',
            '- Ne jamais inventer de routes ou de fonctionnalites.',
            '- Si la demande depasse le contexte, dire ce que tu peux faire a la place.',
            '',
            'Contexte utilisateur:',
            '- Nom: ' . ($fullName !== '' ? $fullName : 'Utilisateur'),
            '- Role principal: ' . $role,
            '- Statut compte: ' . $status,
            '- Segment: ' . $segment,
            '',
            'Actions disponibles:',
            implode("\n", $actionsLines),
        ]);
    }

    private function buildGuestSystemPrompt(): string
    {
        return implode("\n", [
            'Tu es un assistant francophone specialise art et onboarding de la plateforme Art Connect.',
            'Objectif: repondre aux questions sur l art (disciplines, mouvements, techniques, conseils debutants) et guider vers les bonnes pages.',
            'Regles:',
            '- Repondre en francais simple et clair.',
            '- Reponse utile, concise, concrete.',
            '- Tu peux repondre aux questions generales sur l art: peinture, musique, danse, theatre, cinema, photo, art numerique, histoire de l art.',
            '- Si la question demande un avis pratique, proposer 2-4 etapes.',
            '- Quand pertinent, ajouter une suggestion pour utiliser la plateforme (inscription, connexion, evenements, forum).',
            '- Ne pas inventer des routes qui n existent pas.',
        ]);
    }

    /**
     * @return array<int, array{label: string, path: string}>
     */
    private function buildSuggestedActions(User $user): array
    {
        $actions = [
            ['label' => 'Completer mon profil', 'path' => '/participant/profile/edit'],
            ['label' => 'Explorer les evenements', 'path' => '/events/'],
        ];

        $roles = $user->getRoles();
        if (in_array('ROLE_ARTISTE', $roles, true)) {
            $actions = [
                ['label' => 'Completer mon profil artiste', 'path' => '/artist/profile/edit'],
                ['label' => 'Creer mon premier evenement', 'path' => '/events/new'],
                ['label' => 'Voir mes stats artiste', 'path' => '/events/artist/stats'],
            ];
        } elseif (in_array('ROLE_PARTICIPANT', $roles, true)) {
            $actions = [
                ['label' => 'Completer mon profil', 'path' => '/participant/profile/edit'],
                ['label' => 'Trouver un evenement', 'path' => '/events/'],
                ['label' => 'Voir mes commandes', 'path' => '/commandes/mes-commandes'],
            ];
        }

        if ($user->getStatus() !== User::STATUS_APPROVED) {
            array_unshift($actions, [
                'label' => 'Verifier le statut de mon compte',
                'path' => '/participant/profile',
            ]);
        }

        return $actions;
    }

    /**
     * @param array<int, array{label: string, path: string}> $actions
     */
    private function buildFallbackReply(User $user, string $message, array $actions): string
    {
        $mainRole = $this->mainRole($user);
        $status = $user->getStatus();
        $firstAction = $actions[0] ?? null;
        $actionLine = $firstAction ? sprintf('%s (%s)', $firstAction['label'], $firstAction['path']) : 'Explorer les evenements (/events/)';

        $statusHint = '';
        if ($status !== User::STATUS_APPROVED) {
            $statusHint = ' Ton compte est encore en attente de validation finale, certaines actions peuvent etre limitees.';
        }

        return sprintf(
            'Je peux t aider pour ton onboarding %s.%s Vu ton message ("%s"), commence par: %s. Ensuite je te guide etape par etape.',
            $mainRole,
            $statusHint,
            mb_substr($message, 0, 120),
            $actionLine
        );
    }

    private function buildGuestFallbackReply(string $message): string
    {
        return sprintf(
            'Je peux repondre a tes questions sur l art et te guider sur Art Connect. Pour ta question ("%s"), je te conseille de commencer par les evenements artistiques puis de creer un compte pour une aide personnalisee.',
            mb_substr($message, 0, 120)
        );
    }

    private function mainRole(User $user): string
    {
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return 'ADMIN';
        }

        if (in_array('ROLE_ARTISTE', $roles, true)) {
            return 'ARTISTE';
        }

        if (in_array('ROLE_PARTICIPANT', $roles, true)) {
            return 'PARTICIPANT';
        }

        return 'USER';
    }
}
