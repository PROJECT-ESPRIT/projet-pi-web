<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;

class PdfService
{
    private $dompdf;

    public function __construct()
    {
        $this->dompdf = new Dompdf();
        
        // Configuration des options
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('tempDir', sys_get_temp_dir());
        
        $this->dompdf->setOptions($options);
    }

    public function generatePdfFromHtml(string $html, string $filename = 'document.pdf'): Response
    {
        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();

        return new Response(
            $this->dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]
        );
    }

    public function generateForumPostPdf(object $forum): string
    {
        $html = $this->generateForumPostHtml($forum);
        
        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();

        return $this->dompdf->output();
    }

    private function generateForumPostHtml(object $forum): string
    {
        try {
            $date = $forum->getDateCreation() ? $forum->getDateCreation()->format('d/m/Y H:i') : 'N/A';
            $likesCount = method_exists($forum, 'getLikesCount') ? $forum->getLikesCount() : 0;
            $responsesCount = method_exists($forum, 'getReponses') ? count($forum->getReponses()) : 0;
            
            // Échapper les caractères spéciaux pour éviter les erreurs HTML
            $sujet = htmlspecialchars($forum->getSujet(), ENT_QUOTES, 'UTF-8');
            $message = htmlspecialchars($forum->getMessage(), ENT_QUOTES, 'UTF-8');
            $nom = htmlspecialchars($forum->getNom(), ENT_QUOTES, 'UTF-8');
            $prenom = htmlspecialchars($forum->getPrenom(), ENT_QUOTES, 'UTF-8');

            return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Forum Post - {$sujet}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #1E0F75;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #1E0F75;
            margin: 0;
            font-size: 28px;
        }
        .post-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .post-info div {
            margin-bottom: 8px;
        }
        .post-info strong {
            color: #1E0F75;
        }
        .post-content {
            background: white;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .post-title {
            color: #1E0F75;
            font-size: 22px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .post-message {
            white-space: pre-wrap;
            font-size: 14px;
            word-wrap: break-word;
        }
        .stats {
            display: flex;
            gap: 30px;
            background: #f0f8ff;
            padding: 15px;
            border-radius: 8px;
        }
        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class='header'>
        <h1>Forum Post</h1>
        <p>Généré depuis la plateforme communautaire</p>
    </div>

    <div class='post-info'>
        <div><strong>Auteur:</strong> {$nom} {$prenom}</div>
        <div><strong>Date de publication:</strong> {$date}</div>
        <div><strong>ID du post:</strong> #{$forum->getId()}</div>
    </div>

    <div class='post-content'>
        <div class='post-title'>{$sujet}</div>
        <div class='post-message'>{$message}</div>
    </div>

    <div class='stats'>
        <div class='stat-item'>
            <span>❤️</span>
            <span><strong>{$likesCount}</strong> likes</span>
        </div>
        <div class='stat-item'>
            <span>💬</span>
            <span><strong>{$responsesCount}</strong> réponses</span>
        </div>
    </div>

    <div class='footer'>
        <p>Document généré le " . date('d/m/Y H:i:s') . "</p>
        <p>Plateforme Communautaire - Tous droits réservés</p>
    </div>
</body>
</html>";
        } catch (\Exception $e) {
            // Retourner un HTML de base en cas d'erreur
            return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .error { color: red; text-align: center; }
    </style>
</head>
<body>
    <div class='error'>
        <h2>Erreur lors de la génération du PDF</h2>
        <p>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>
    </div>
</body>
</html>";
        }
    }
}
