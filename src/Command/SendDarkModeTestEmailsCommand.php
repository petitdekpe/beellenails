<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Command;

use App\Entity\User;
use App\Entity\Rendezvous;
use App\Entity\Prestation;
use App\Entity\Creneau;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

#[AsCommand(
    name: 'app:send-darkmode-test-emails',
    description: 'Envoie tous les types d\'emails de test pour tester le mode sombre',
)]
class SendDarkModeTestEmailsCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse email de destination')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Type d\'email à envoyer (all, rendezvous, reset, registration)', 'all')
            ->setHelp('Cette commande envoie différents types d\'emails pour tester le mode sombre');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $emailAddress = $input->getArgument('email');
        $type = $input->getOption('type');

        $io->title('🌙 Test des emails en mode sombre');
        $io->text("Envoi vers : <info>$emailAddress</info>");

        // Créer des données de test
        $testUser = $this->createTestUser();
        $testRendezvous = $this->createTestRendezvous($testUser);

        try {
            switch ($type) {
                case 'rendezvous':
                    $this->sendRendezvousEmails($emailAddress, $testRendezvous, $io);
                    break;
                case 'reset':
                    $this->sendResetPasswordEmail($emailAddress, $testUser, $io);
                    break;
                case 'registration':
                    $this->sendRegistrationEmail($emailAddress, $testUser, $io);
                    break;
                case 'all':
                default:
                    $this->sendAllTestEmails($emailAddress, $testUser, $testRendezvous, $io);
                    break;
            }

            $io->success('Tous les emails de test ont été envoyés avec succès !');
            $io->note('Vérifiez votre boîte email et activez le mode sombre pour voir les adaptations.');

        } catch (\Exception $e) {
            $io->error('Erreur lors de l\'envoi des emails : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function sendAllTestEmails(string $emailAddress, User $testUser, Rendezvous $testRendezvous, SymfonyStyle $io): void
    {
        $this->sendRendezvousEmails($emailAddress, $testRendezvous, $io);
        $this->sendResetPasswordEmail($emailAddress, $testUser, $io);
        $this->sendRegistrationEmail($emailAddress, $testUser, $io);
    }

    private function sendRendezvousEmails(string $emailAddress, Rendezvous $testRendezvous, SymfonyStyle $io): void
    {
        // Email de confirmation de RDV
        $io->text('📅 Envoi de l\'email de confirmation de rendez-vous...');
        $this->sendEmail(
            $emailAddress,
            'Confirmation de votre rendez-vous - BeElle Nails (Test Mode Sombre)',
            'emails/rendezvous_created.html.twig',
            ['rendezvous' => $testRendezvous]
        );

        // Email de rappel de RDV
        $io->text('⏰ Envoi de l\'email de rappel de rendez-vous...');
        $this->sendEmail(
            $emailAddress,
            'Rappel de votre rendez-vous demain - BeElle Nails (Test Mode Sombre)',
            'emails/rendezvous_reminder.html.twig',
            ['rendezvous' => $testRendezvous]
        );

        // Email d'annulation de RDV
        $io->text('❌ Envoi de l\'email d\'annulation de rendez-vous...');
        $this->sendEmail(
            $emailAddress,
            'Annulation de votre rendez-vous - BeElle Nails (Test Mode Sombre)',
            'emails/rendezvous_canceled.html.twig',
            ['rendezvous' => $testRendezvous]
        );
    }

    private function sendResetPasswordEmail(string $emailAddress, User $testUser, SymfonyStyle $io): void
    {
        $io->text('🔐 Envoi de l\'email de réinitialisation de mot de passe...');
        
        // Créer un objet mock pour le test
        $resetTokenMock = new class('test-token-' . uniqid(), new \DateTimeImmutable('+1 hour'), time() + 3600) {
            public function __construct(
                private string $token,
                private \DateTimeImmutable $expiresAt,
                private int $generatedAt
            ) {}

            public function getToken(): string
            {
                return $this->token;
            }

            public function getExpiresAt(): \DateTimeImmutable
            {
                return $this->expiresAt;
            }

            public function getExpirationMessageKey(): string
            {
                return 'Cette demande expire dans 1 heure.';
            }

            public function getExpirationMessageData(): array
            {
                return ['%count%' => 1, '%unit%' => 'heure'];
            }
        };

        $this->sendEmail(
            $emailAddress,
            'Réinitialisation de votre mot de passe - BeElle Nails (Test Mode Sombre)',
            'reset_password/email.html.twig',
            ['resetToken' => $resetTokenMock]
        );
    }

    private function sendRegistrationEmail(string $emailAddress, User $testUser, SymfonyStyle $io): void
    {
        $io->text('👤 Envoi de l\'email de bienvenue inscription...');
        $this->sendEmail(
            $emailAddress,
            'Bienvenue sur BeElle Nails (Test Mode Sombre)',
            'registration/email.html.twig',
            ['user' => $testUser]
        );
    }

    private function sendEmail(string $to, string $subject, string $template, array $context = []): void
    {
        $htmlBody = $this->twig->render($template, $context);

        $email = (new Email())
            ->from('beellenailscare@beellenails.com')
            ->to($to)
            ->subject($subject)
            ->html($htmlBody);

        $this->mailer->send($email);
    }

    private function createTestUser(): User
    {
        $user = new User();
        $user->setNom('Testeur');
        $user->setPrenom('Mode Sombre');
        $user->setEmail('test@example.com');
        $user->setPhone('+229 97 85 35 12');
        
        return $user;
    }

    private function createTestRendezvous(User $user): Rendezvous
    {
        // Récupérer une prestation existante ou créer une prestation de test
        $prestation = $this->entityManager->getRepository(Prestation::class)->findOneBy([]) 
                     ?? $this->createTestPrestation();

        // Récupérer un créneau existant ou créer un créneau de test
        $creneau = $this->entityManager->getRepository(Creneau::class)->findOneBy([])
                  ?? $this->createTestCreneau();

        $rendezvous = new Rendezvous();
        $rendezvous->setUser($user);
        $rendezvous->setPrestation($prestation);
        $rendezvous->setCreneau($creneau);
        $rendezvous->setDay(new \DateTime('+1 day'));
        $rendezvous->setStatus('Confirmé');
        
        return $rendezvous;
    }

    private function createTestPrestation(): Prestation
    {
        $prestation = new Prestation();
        $prestation->setTitle('Manucure Russe Complète (Test)');
        $prestation->setNom('Manucure Russe Complète (Test)');
        $prestation->setPrix(25000);
        
        return $prestation;
    }

    private function createTestCreneau(): Creneau
    {
        $creneau = new Creneau();
        $creneau->setLibelle('10:00 - 12:00 (Test)');
        $creneau->setStartTime(new \DateTime('10:00'));
        $creneau->setEndTime(new \DateTime('12:00'));
        
        return $creneau;
    }
}