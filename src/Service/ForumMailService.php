<?php

namespace App\Service;

use App\Entity\ForumReponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Psr\Log\LoggerInterface;

class ForumMailService
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;

    public function __construct(MailerInterface $mailer, LoggerInterface $logger)
    {
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    /**
     * Envoie un email de notification dynamique lorsqu'un commentaire est ajouté à un post
     * 
     * @param ForumReponse $comment Le commentaire ajouté
     * @return bool True si l'email a été envoyé avec succès, false sinon
     */
    public function sendDynamicCommentNotification(ForumReponse $comment): bool
    {
        $this->logger->info('Début envoi notification commentaire', [
            'comment_id' => $comment->getId(),
            'forum_id' => $comment->getForum()?->getId()
        ]);

        try {
            $forum = $comment->getForum();
            $commentAuthor = $comment->getAuteur();

            // Validation des données requises
            if (!$forum || !$commentAuthor) {
                $this->logger->error('Données manquantes pour envoi email', [
                    'forum_exists' => $forum !== null,
                    'author_exists' => $commentAuthor !== null
                ]);
                return false;
            }

            // Récupération des emails
            $fromEmail = $commentAuthor->getEmail(); // Expéditeur = auteur du commentaire
            $toEmail = $forum->getEmail(); // Destinataire = créateur du post

            if (!$fromEmail || !$toEmail) {
                $this->logger->error('Emails manquants', [
                    'from_email' => $fromEmail,
                    'to_email' => $toEmail
                ]);
                return false;
            }

            // Création de l'email avec template
            $email = (new TemplatedEmail())
                ->from($fromEmail)
                ->to($toEmail)
                ->subject('Nouveau commentaire sur votre post')
                ->htmlTemplate('emails/forum_comment_notification.html.twig')
                ->context([
                    'post_title' => $forum->getSujet(),
                    'post_content' => $forum->getMessage(),
                    'comment_content' => $comment->getContenu(),
                    'comment_author_name' => $commentAuthor->getFullName(),
                    'comment_author_email' => $fromEmail,
                    'comment_date' => $comment->getDateReponse(),
                    'post_author_name' => $forum->getPrenom() . ' ' . $forum->getNom(),
                    'forum' => $forum,
                    'comment' => $comment
                ]);

            // Envoi de l'email
            $this->mailer->send($email);

            $this->logger->info('Email de notification envoyé avec succès', [
                'comment_id' => $comment->getId(),
                'from' => $fromEmail,
                'to' => $toEmail
            ]);

            return true;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur de transport email', [
                'comment_id' => $comment->getId(),
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Erreur inattendue envoi email', [
                'comment_id' => $comment->getId(),
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
