<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class ResetPasswordMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail,
        private int $passwordResetTtlMinutes,
    ) {}

    public function send(string $to, string $code): void
    {
        $email = (new TemplatedEmail())
            ->from($this->fromEmail)
            ->to($to)
            ->subject('Code de réinitialisation — Art Connect')
            ->htmlTemplate('emails/reset_password.html.twig')
            ->context([
                'code' => $code,
                'ttlMinutes' => $this->passwordResetTtlMinutes,
            ]);

        $this->mailer->send($email);
    }
}
