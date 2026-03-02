<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
<<<<<<< HEAD
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
=======
use Symfony\Component\Mime\Email;
>>>>>>> c4d1c44b0746a7387dc28bd3111400a167bda2d9

class ResetPasswordMailer
{
    public function __construct(
        private MailerInterface $mailer,
<<<<<<< HEAD
        private string $fromEmail,
        private int $passwordResetTtlMinutes,
=======
        private string $fromEmail
>>>>>>> c4d1c44b0746a7387dc28bd3111400a167bda2d9
    ) {}

    public function send(string $to, string $code): void
    {
<<<<<<< HEAD
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
=======
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
>>>>>>> c4d1c44b0746a7387dc28bd3111400a167bda2d9
