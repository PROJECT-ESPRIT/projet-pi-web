<?php

namespace App\Service;

use App\Entity\Reservation;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class TicketPdfService
{
    public function __construct(
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        private ParameterBagInterface $params,
        private string $projectDir,
    ) {}

    public function generateQrCodeDataUri(Reservation $reservation): string
    {
        $secret = $this->params->get('kernel.secret');
        $token = hash_hmac('sha256', (string) $reservation->getId(), $secret);
        $scanUrl = $this->urlGenerator->generate('app_reservation_scan', [
            'id' => $reservation->getId(),
            'token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $qrPayload = $scanUrl;

        $builder = new Builder(
            writer: new SvgWriter(),
            data: $qrPayload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 280,
            margin: 12,
        );
        $result = $builder->build();

        return $result->getDataUri();
    }

    public function generatePdf(Reservation $reservation): string
    {
        $qrDataUri = $this->generateQrCodeDataUri($reservation);

        $evenement = $reservation->getEvenement();
        $participant = $reservation->getParticipant();

        $eventImageDataUri = null;
        if ($evenement->getImage()) {
            $relative = ltrim($evenement->getImage(), '/\\');
            $fullPath = $this->projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
            if (is_file($fullPath)) {
                $mime = 'image/jpeg';
                $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                if ($ext === 'png') {
                    $mime = 'image/png';
                } elseif ($ext === 'gif') {
                    $mime = 'image/gif';
                } elseif ($ext === 'webp') {
                    $mime = 'image/webp';
                }
                $eventImageDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullPath));
            }
        }

        $html = $this->twig->render('emails/ticket_pdf.html.twig', [
            'reservation' => $reservation,
            'evenement' => $evenement,
            'participant' => $participant,
            'qr_code' => $qrDataUri,
            'event_image_data_uri' => $eventImageDataUri,
            'participant_name' => trim($participant->getPrenom() . ' ' . $participant->getNom()),
            'event_date' => $evenement->getDateDebut() ? $evenement->getDateDebut()->format('d/m/Y à H:i') : '',
            'event_date_end' => $evenement->getDateFin() ? $evenement->getDateFin()->format('d/m/Y à H:i') : '',
            'lieu' => $evenement->getLieu(),
            'seat_label' => $reservation->getSeatLabel(),
            'prix' => $evenement->getPrix() ? number_format($evenement->getPrix(), 2, ',', ' ') . ' TND' : 'Gratuit',
            'reservation_date' => $reservation->getDateReservation() ? $reservation->getDateReservation()->format('d/m/Y à H:i') : '',
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A5', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }
}
