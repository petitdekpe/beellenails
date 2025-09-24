<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Service;

use App\Entity\FormationEnrollment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class CertificateService
{
    public function __construct(
        private Environment $twig
    ) {}

    public function generateCertificate(FormationEnrollment $enrollment): string
    {
        // Configure DOMPDF
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        
        $dompdf = new Dompdf($options);

        // Generate HTML for certificate
        $html = $this->twig->render('certificates/formation_certificate.html.twig', [
            'enrollment' => $enrollment,
            'user' => $enrollment->getUser(),
            'formation' => $enrollment->getFormation(),
            'completedAt' => $enrollment->getCompletedAt(),
            'certificateNumber' => $this->generateCertificateNumber($enrollment),
        ]);

        // Generate PDF
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    private function generateCertificateNumber(FormationEnrollment $enrollment): string
    {
        $date = $enrollment->getCompletedAt() ? $enrollment->getCompletedAt()->format('Ymd') : date('Ymd');
        $userId = str_pad($enrollment->getUser()->getId(), 4, '0', STR_PAD_LEFT);
        $formationId = str_pad($enrollment->getFormation()->getId(), 3, '0', STR_PAD_LEFT);
        
        return 'BEN-' . $date . '-' . $userId . '-' . $formationId;
    }
}