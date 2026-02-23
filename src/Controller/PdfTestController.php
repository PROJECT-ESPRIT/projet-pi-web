<?php

namespace App\Controller;

use App\Entity\Forum;
use App\Repository\ForumRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/test-pdf')]
final class PdfTestController extends AbstractController
{
    #[Route('/simple/{id<\\d+>}', name: 'app_pdf_test_simple', methods: ['GET'])]
    public function simplePdf(Forum $forum): Response
    {
        try {
            // Créer un HTML simple pour le PDF
            $html = $this->createSimplePdfHtml($forum);
            
            // Utiliser une méthode basique pour générer le PDF
            $pdfContent = $this->generateBasicPdf($html);
            
            $filename = 'forum-simple-' . $forum->getId() . '.pdf';
            
            return new Response(
                $pdfContent,
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    'Content-Length' => strlen($pdfContent)
                ]
            );
        } catch (\Exception $e) {
            return new Response(
                'Erreur: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    private function createSimplePdfHtml(Forum $forum): string
    {
        $date = $forum->getDateCreation() ? $forum->getDateCreation()->format('d/m/Y H:i') : 'N/A';
        $sujet = htmlspecialchars($forum->getSujet(), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($forum->getMessage(), ENT_QUOTES, 'UTF-8');
        
        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial; padding: 20px; }
        h1 { color: #1E0F75; }
        .info { background: #f5f5f5; padding: 10px; margin: 10px 0; }
        .content { border: 1px solid #ddd; padding: 15px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Forum Post #{$forum->getId()}</h1>
    <div class='info'>
        <strong>Auteur:</strong> {$forum->getNom()} {$forum->getPrenom()}<br>
        <strong>Date:</strong> {$date}
    </div>
    <div class='content'>
        <h2>{$sujet}</h2>
        <p>{$message}</p>
    </div>
    <hr>
    <small>Généré le " . date('d/m/Y H:i:s') . "</small>
</body>
</html>";
    }
    
    private function generateBasicPdf(string $html): string
    {
        // Pour l'instant, retourner le HTML comme texte
        // Ceci est une solution de secours
        return "PDF Generation Failed - HTML Content:\n\n" . $html;
    }
}
