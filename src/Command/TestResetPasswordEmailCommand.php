<?php

namespace App\Command;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

#[AsCommand(
    name: 'app:test-reset-password-email',
    description: 'Envoie un email de test pour la réinitialisation de mot de passe'
)]
class TestResetPasswordEmailCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse email de destination')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $emailAddress = $input->getArgument('email');

        // Créer un token de test
        $fakeToken = 'test_token_' . bin2hex(random_bytes(16));
        
        // Créer un objet ResetPasswordToken factice pour le test
        $resetToken = new ResetPasswordToken(
            $fakeToken,
            new \DateTimeImmutable('+1 hour'), // Expire dans 1 heure
            3600 // 1 heure en secondes
        );

        try {
            $email = (new TemplatedEmail())
                ->from(new Address('beellenailscare@beellenails.com', 'BeElle Nails'))
                ->to($emailAddress)
                ->subject('Réinitialisation de votre mot de passe - BeElle Nails')
                ->htmlTemplate('reset_password/email.html.twig')
                ->context([
                    'resetToken' => $resetToken,
                ]);

            $this->mailer->send($email);
            $io->success('Email de réinitialisation de mot de passe envoyé avec succès !');
        } catch (\Exception $e) {
            $io->error('Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}