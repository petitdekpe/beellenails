<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

#[AsCommand(
    name: 'app:test-reset-password-flow',
    description: 'Teste le processus complet de réinitialisation de mot de passe'
)]
class TestResetPasswordFlowCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private MailerInterface $mailer,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse email pour le test')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $testEmail = $input->getArgument('email');

        // Créer ou trouver un utilisateur de test
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $testEmail]);
        
        if (!$user) {
            $user = new User();
            $user->setEmail($testEmail);
            $user->setNom('Test');
            $user->setPrenom('User');
            $user->setPhone('97853512');
            $user->setPassword($this->passwordHasher->hashPassword($user, 'oldPassword123'));
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            $io->note('Utilisateur de test créé avec l\'email : ' . $testEmail);
        }

        try {
            // Générer le token de réinitialisation
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
            
            // Envoyer l'email
            $email = (new TemplatedEmail())
                ->from(new Address('beellenailscare@beellenails.com', 'BeElle Nails'))
                ->to($user->getEmail())
                ->subject('Réinitialisation de votre mot de passe - BeElle Nails')
                ->htmlTemplate('reset_password/email.html.twig')
                ->context([
                    'resetToken' => $resetToken,
                ]);

            $this->mailer->send($email);
            
            $io->success('Email de réinitialisation envoyé avec succès !');
            $io->note('URL de réinitialisation (pour test) : /reset-password/reset/' . $resetToken->getToken());
            
        } catch (\Exception $e) {
            $io->error('Erreur lors du processus : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}