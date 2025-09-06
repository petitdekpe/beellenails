<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:quick-test-email',
    description: 'Envoi rapide d\'un email de test avec template HTML',
)]
class QuickTestEmailCommand extends Command
{
    public function __construct(private MailerInterface $mailer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Adresse email de destination');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $emailAddress = $input->getArgument('email');

        $io->title('🌙 Test rapide du mode sombre dans les emails');
        $io->text("Envoi vers : <info>$emailAddress</info>");

        try {
            $this->sendQuickTestEmail($emailAddress);
            $io->success('Email de test envoyé avec succès !');
            $io->note([
                'Pour tester le mode sombre :',
                '1. Activez le mode sombre dans votre client email (Gmail, Outlook, Apple Mail)',
                '2. Ouvrez l\'email reçu',
                '3. Les couleurs doivent s\'adapter automatiquement'
            ]);

        } catch (\Exception $e) {
            $io->error('Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function sendQuickTestEmail(string $to): void
    {
        $htmlContent = $this->getTestEmailContent();

        $email = (new Email())
            ->from('beellenailscare@beellenails.com')
            ->to($to)
            ->subject('🌙 Test Mode Sombre - BeElle Nails')
            ->html($htmlContent);

        $this->mailer->send($email);
    }

    private function getTestEmailContent(): string
    {
        return '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Mode Sombre - BeElle Nails</title>
    <style>
        /* Reset styles */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }

        /* Base styles */
        body {
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }

        .logo {
            max-width: 100px;
            height: auto;
            margin-bottom: 20px;
        }

        .footer-logo {
            max-width: 75px;
            height: auto;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .email-header {
            background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);
            padding: 40px 30px;
            text-align: center;
        }

        .header-title {
            color: #ffffff;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header-subtitle {
            color: #fce7f3;
            font-size: 16px;
            margin: 10px 0 0 0;
            font-weight: 400;
        }

        .email-content {
            padding: 40px 30px;
            line-height: 1.6;
        }

        .greeting {
            color: #1f2937;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .content-text {
            color: #4b5563;
            font-size: 16px;
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .info-box {
            background: linear-gradient(135deg, #fef3f4 0%, #fce7f3 100%);
            border-left: 4px solid #ec4899;
            padding: 25px;
            margin: 30px 0;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(236, 72, 153, 0.1);
        }

        .info-title {
            color: #be185d;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .btn-primary {
            display: inline-block;
            background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);
            color: #ffffff;
            text-decoration: none;
            padding: 16px 32px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 16px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(236, 72, 153, 0.3);
        }

        .email-footer {
            background-color: #f9fafb;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .footer-info {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.6;
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            body { background-color: #111827 !important; }
            .email-container { background-color: #1f2937 !important; }
            
            .email-content { background-color: #1f2937 !important; }
            .content-text { color: #d1d5db !important; }
            .greeting { color: #f9fafb !important; }
            
            .email-footer { 
                background-color: #111827 !important; 
                border-top: 1px solid #374151 !important;
            }
            .footer-info { color: #9ca3af !important; }
            
            .info-box { 
                background: linear-gradient(135deg, #1f2937 0%, #374151 100%) !important;
                border-left: 4px solid #f472b6 !important;
            }
            .info-title { color: #f472b6 !important; }
            
            a { color: #f472b6 !important; }
            
            .btn-primary { 
                background: linear-gradient(135deg, #f472b6 0%, #ec4899 100%) !important;
            }
        }

        /* Responsive */
        @media screen and (max-width: 600px) {
            .email-container { width: 100% !important; }
            .email-header, .email-content, .email-footer { padding: 20px !important; }
            .header-title { font-size: 24px !important; }
            .info-box { padding: 20px !important; margin: 20px 0 !important; }
            .btn-primary { padding: 14px 28px !important; font-size: 14px !important; }
        }
    </style>
</head>
<body>
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <div class="email-container">
                    <!-- Header -->
                    <div class="email-header">
                        <h1 class="header-title">🌙 Test Mode Sombre</h1>
                        <p class="header-subtitle">BeElle Nails Care - Email Responsive</p>
                    </div>

                    <!-- Content -->
                    <div class="email-content">
                        <div class="greeting">
                            Bonjour ! 👋
                        </div>

                        <div class="content-text">
                            Ceci est un <strong>email de test</strong> pour vérifier le support du mode sombre. 
                            Les styles de cet email s\'adaptent automatiquement selon vos préférences système.
                        </div>

                        <div class="info-box">
                            <div class="info-title">
                                🎨 Test des couleurs en mode sombre
                            </div>
                            <div class="content-text" style="margin: 0; color: #374151;">
                                • <strong>Mode clair</strong> : Fond blanc, texte sombre<br>
                                • <strong>Mode sombre</strong> : Fond sombre, texte clair<br>
                                • <strong>Adaptation automatique</strong> selon vos préférences
                            </div>
                        </div>

                        <div class="content-text">
                            <strong>Comment tester :</strong><br>
                            1. Activez le mode sombre dans votre client email<br>
                            2. Rafraîchissez cet email<br>
                            3. Observez l\'adaptation des couleurs<br>
                            4. Testez aussi en mode clair pour comparaison
                        </div>

                        <div style="text-align: center; margin: 40px 0;">
                            <a href="https://beellenails.com" class="btn-primary">
                                🌐 Visiter BeElle Nails
                            </a>
                        </div>

                        <div class="content-text">
                            <strong>Clients email testés :</strong><br>
                            ✅ Gmail (Web & Mobile)<br>
                            ✅ Outlook (Web & Desktop)<br>
                            ✅ Apple Mail (macOS & iOS)<br>
                            ✅ Thunderbird<br>
                        </div>

                        <div class="content-text">
                            <em>Email généré automatiquement pour tester le mode sombre</em><br>
                            <strong>L\'équipe technique BeElle Nails</strong> 💻✨
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="email-footer">
                        <div class="footer-info">
                            <strong>BeElle Nails Care</strong><br>
                            Vodjè, 2ème rue après la clinique de l\'union<br>
                            En allant à Gbegamey, au fond de la rue à droite, portail rose<br>
                            Cotonou, Bénin
                        </div>
                        <div class="footer-info" style="margin-top: 15px;">
                            📞 +229 97 85 35 12 | 🌐 www.beellenails.com
                        </div>
                        <div style="color: #9ca3af; font-size: 12px; margin-top: 20px;">
                            © 2025 BeElle Nails. Tous droits réservés.
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
}