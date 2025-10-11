<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Service;

use App\Entity\Payment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ReceiptPdfService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly ParameterBagInterface $params
    ) {}

    public function generateReceiptPdf(Payment $payment, $entity): string
    {
        // Configure Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);

        // Convert logo to base64
        $logoPath = $this->params->get('kernel.project_dir') . '/public/assets/logooff.png';
        $logoBase64 = null;

        if (file_exists($logoPath)) {
            $imageData = file_get_contents($logoPath);
            $imageType = mime_content_type($logoPath);
            $logoBase64 = 'data:' . $imageType . ';base64,' . base64_encode($imageData);
        }

        // Render the Twig template
        $html = $this->twig->render('payment/receipt.html.twig', [
            'payment' => $payment,
            'entity' => $entity,
            'logoBase64' => $logoBase64
        ]);

        // Load HTML content
        $dompdf->loadHtml($html);

        // Set paper size
        $dompdf->setPaper('A4', 'portrait');

        // Render PDF
        $dompdf->render();

        // Return PDF content
        return $dompdf->output();
    }
}
