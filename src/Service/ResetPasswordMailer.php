<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ResetPasswordMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail
    ) {}

    public function send(string $to, string $code): void
    {
        $email = (new Email())
            ->from($this->fromEmail)
            ->to($to)
            ->subject('Code de vérification')
            ->text(
                "Bonjour,\n\n".
                "Votre code de vérification est : $code\n\n".
                "Il est valable 15 minutes."
            );

        $this->mailer->send($email);
    }
}