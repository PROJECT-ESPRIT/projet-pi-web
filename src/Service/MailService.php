<?php

namespace App\Service;

use App\Entity\ForumReponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Psr\Log\LoggerInterface;

class MailService
{
    private MailerInterface $mailer;
    private string $fromEmail;
    private string $toEmail;
    private LoggerInterface $logger;

    public function __construct(MailerInterface $mailer, LoggerInterface $logger, string $fromEmail = 'dorbezrayen100@gmail.com', string $toEmail = 'rayen.dorbez@esprit.tn')
    {
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->fromEmail = $fromEmail;
        $this->toEmail = $toEmail;
        
        // Log pour vérifier l'initialisation
        $this->logger->info('MailService initialisé', [
            'from' => $this->fromEmail,
            'to' => $this->toEmail
        ]);
    }

    /**
     * Envoie un email de notification dynamique lorsqu'un commentaire est ajouté à un post
     * Destinataires: TO = auteur du post, CC = auteur du commentaire
     */
    public function sendDynamicCommentNotification(ForumReponse $comment): bool
    {
        $this->logger->info('Tentative d\'envoi d\'email de notification dynamique', [
            'comment_id' => $comment->getId(),
            'forum_id' => $comment->getForum()?->getId(),
            'author_id' => $comment->getAuteur()?->getId()
        ]);
        
        try {
            $forum = $comment->getForum();
            $commentAuthor = $comment->getAuteur();
            
            if (!$forum || !$commentAuthor) {
                $this->logger->error('Données manquantes pour l\'envoi d\'email', [
                    'forum_exists' => $forum !== null,
                    'author_exists' => $commentAuthor !== null
                ]);
                return false;
            }
            
            // Récupérer l'email de l'auteur du post (destinataire principal)
            $postAuthorEmail = $forum->getEmail(); // Le Forum contient l'email du créateur
            $commentAuthorEmail = $commentAuthor->getEmail(); // L'email de l'auteur du commentaire
            
            if (!$postAuthorEmail) {
                $this->logger->error('Email de l\'auteur du post manquant', [
                    'forum_id' => $forum->getId()
                ]);
                return false;
            }
            
            // Créer l'email avec TemplatedEmail
            $email = (new TemplatedEmail())
                ->from($this->fromEmail)
                ->to($postAuthorEmail) // TO: auteur du post
                ->subject('Nouveau commentaire sur votre post: ' . $forum->getSujet())
                ->htmlTemplate('emails/dynamic_comment_notification.html.twig')
                ->context([
                    'post_title' => $forum->getSujet(),
                    'post_content' => $forum->getMessage(),
                    'comment_content' => $comment->getContenu(),
                    'comment_author_name' => $commentAuthor->getFullName(),
                    'comment_author_email' => $commentAuthorEmail,
                    'comment_date' => $comment->getDateReponse(),
                    'post_author_name' => $forum->getPrenom() . ' ' . $forum->getNom(),
                    'forum_id' => $forum->getId(),
                    'comment_id' => $comment->getId()
                ]);
            
            // Ajouter l'auteur du commentaire en CC si son email est différent de l'auteur du post
            if ($commentAuthorEmail && $commentAuthorEmail !== $postAuthorEmail) {
                $email->cc($commentAuthorEmail);
            }
            
            $this->logger->info('Email créé, tentative d\'envoi...', [
                'to' => $postAuthorEmail,
                'cc' => $commentAuthorEmail,
                'subject' => $email->getSubject()
            ]);
            
            $this->mailer->send($email);
            
            $this->logger->info('Email de notification envoyé avec succès', [
                'comment_id' => $comment->getId(),
                'to' => $postAuthorEmail,
                'cc' => $commentAuthorEmail
            ]);
            
            return true;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur de transport lors de l\'envoi d\'email de notification', [
                'comment_id' => $comment->getId(),
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Erreur inattendue lors de l\'envoi d\'email de notification', [
                'comment_id' => $comment->getId(),
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Version alternative avec Email simple (sans template)
     */
    public function sendSimpleCommentNotification(string $postTitle, string $commentContent, string $authorName, \DateTimeImmutable $commentDate): bool
    {
        try {
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($this->toEmail)
                ->subject('Nouveau commentaire sur votre post')
                ->html($this->generateEmailContent($postTitle, $commentContent, $authorName, $commentDate));

            $this->mailer->send($email);
            return true;

        } catch (TransportExceptionInterface $e) {
            return false;
        }
    }

    private function generateEmailContent(string $postTitle, string $commentContent, string $authorName, \DateTimeImmutable $commentDate): string
    {
        return "
        <h2>Nouveau commentaire sur le forum</h2>
        <p><strong>Titre du post:</strong> {$postTitle}</p>
        <p><strong>Auteur du commentaire:</strong> {$authorName}</p>
        <p><strong>Date du commentaire:</strong> {$commentDate->format('d/m/Y H:i')}</p>
        <hr>
        <h3>Contenu du commentaire:</h3>
        <p>" . nl2br(htmlspecialchars($commentContent)) . "</p>
        <hr>
        <p><em>Cet email a été envoyé automatiquement par le système de forum.</em></p>
        ";
    }
}
