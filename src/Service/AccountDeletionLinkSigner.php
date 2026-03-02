<?php

namespace App\Service;

use App\Entity\User;

class AccountDeletionLinkSigner
{
    public function __construct(
        private readonly string $appSecret,
        private readonly int $accountDeletionTtlMinutes,
    ) {
    }

    /**
     * @return array{uid: int, exp: int, sig: string}
     */
    public function generateFor(User $user): array
    {
        $expiresAt = time() + ($this->accountDeletionTtlMinutes * 60);

        return [
            'uid' => (int) $user->getId(),
            'exp' => $expiresAt,
            'sig' => $this->sign($user, $expiresAt),
        ];
    }

    public function isValid(User $user, int $expiresAt, string $signature): bool
    {
        if ($expiresAt <= time()) {
            return false;
        }

        $expected = $this->sign($user, $expiresAt);

        return hash_equals($expected, $signature);
    }

    public function getTtlMinutes(): int
    {
        return $this->accountDeletionTtlMinutes;
    }

    private function sign(User $user, int $expiresAt): string
    {
        $payload = implode('|', [
            (string) $user->getId(),
            (string) $user->getEmail(),
            (string) $expiresAt,
        ]);

        return hash_hmac('sha256', $payload, $this->appSecret);
    }
}
