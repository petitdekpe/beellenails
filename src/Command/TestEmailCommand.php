<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>


namespace App\Command;

use App\Entity\Rendezvous;
use App\Entity\User;
use App\Entity\Prestation;
use App\Entity\Creneau;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

#[AsCommand(
    name: 'app:test-email',
    description: 'Envoie un email de test avec des données d\'exemple'
)]
class TestEmailCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse email de destination')
            ->addArgument('type', InputArgument::REQUIRED, 'Type d\'email à tester (created, canceled, reminder, updated)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $emailAddress = $input->getArgument('email');
        $emailType = $input->getArgument('type');

        // Créer des données de test
        $testUser = $this->createTestUser();
        $testRendezvous = $this->createTestRendezvous($testUser);

        try {
            switch ($emailType) {
                case 'created':
                    $this->sendCreatedEmail($emailAddress, $testRendezvous);
                    $io->success('Email de confirmation de rendez-vous envoyé avec succès !');
                    break;
                    
                case 'canceled':
                    $this->sendCanceledEmail($emailAddress, $testRendezvous);
                    $io->success('Email d\'annulation de rendez-vous envoyé avec succès !');
                    break;
                    
                case 'reminder':
                    $this->sendReminderEmail($emailAddress, $testRendezvous);
                    $io->success('Email de rappel de rendez-vous envoyé avec succès !');
                    break;
                    
                case 'updated':
                    $this->sendUpdatedEmail($emailAddress, $testRendezvous);
                    $io->success('Email de modification de rendez-vous envoyé avec succès !');
                    break;
                    
                case 'admin_created':
                    $email = (new TemplatedEmail())
                        ->from(new Address('beellenailscare@beellenails.com', 'BeElle Nails'))
                        ->to($emailAddress)
                        ->subject('Nouveau rendez-vous pris - BeElle Nails')
                        ->htmlTemplate('emails/rendezvous_created_admin.html.twig')
                        ->context([
                            'rendezvous' => $testRendezvous,
                        ]);
                    $this->mailer->send($email);
                    $io->success('Email admin de nouveau rendez-vous envoyé avec succès !');
                    break;

                case 'admin_canceled':
                    $email = (new TemplatedEmail())
                        ->from(new Address('beellenailscare@beellenails.com', 'BeElle Nails'))
                        ->to($emailAddress)
                        ->subject('Rendez-vous annulé - BeElle Nails')
                        ->htmlTemplate('emails/rendezvous_canceled_admin.html.twig')
                        ->context([
                            'rendezvous' => $testRendezvous,
                        ]);
                    $this->mailer->send($email);
                    $io->success('Email admin d\'annulation de rendez-vous envoyé avec succès !');
                    break;

                case 'admin_updated':
                    $email = (new TemplatedEmail())
                        ->from(new Address('beellenailscare@beellenails.com', 'BeElle Nails'))
                        ->to($emailAddress)
                        ->subject('Rendez-vous modifié - BeElle Nails')
                        ->htmlTemplate('emails/rendezvous_updated_admin.html.twig')
                        ->context([
                            'rendezvous' => $testRendezvous,
                        ]);
                    $this->mailer->send($email);
                    $io->success('Email admin de modification de rendez-vous envoyé avec succès !');
                    break;
                    
                default:
                    $io->error('Type d\'email non valide. Utilisez: created, canceled, reminder, updated, admin_created, admin_canceled, admin_updated');
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function createTestUser(): User
    {
        $user = new User();
        $user->setNom('Doe');
        $user->setPrenom('Jane');
        $user->setEmail('jane.doe@example.com');
        $user->setPhone('97853512');
        
        return $user;
    }

    private function createTestRendezvous(User $user): Rendezvous
    {
        // Récupérer une vraie prestation de la base de données
        $prestation = $this->entityManager->getRepository(Prestation::class)->findOneBy([]);
        if (!$prestation) {
            // Créer une prestation de test si aucune n'existe
            $prestation = new Prestation();
            $prestation->setTitle('Manucure Complète');
            $prestation->setDescription('Soin complet des ongles avec pose de vernis');
            $prestation->setPrice('15000');
            $prestation->setDuration(60);
        }

        // Récupérer un vrai créneau de la base de données
        $creneau = $this->entityManager->getRepository(Creneau::class)->findOneBy([]);
        if (!$creneau) {
            // Créer un créneau de test si aucun n'existe
            $creneau = new Creneau();
            $creneau->setStartTime(new \DateTime('14:00'));
            $creneau->setEndTime(new \DateTime('15:00'));
        }

        $rendezvous = new Rendezvous();
        $rendezvous->setUser($user);
        $rendezvous->setPrestation($prestation);
        $rendezvous->setCreneau($creneau);
        $rendezvous->setDay(new \DateTime('tomorrow'));
        $rendezvous->setStatus('Rendez-vous confirmé');
        $rendezvous->setTotalCost('15000');
        $rendezvous->setImageName('default.png');

        return $rendezvous;
    }

    private function sendCreatedEmail(string $emailAddress, Rendezvous $rendezvous): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address('beellenailscare@beellenails.com', 'BeElle Nails'))
            ->to($emailAddress)
            ->subject('Confirmation de votre rendez-vous - BeElle Nails')
            ->htmlTemplate('emails/rendezvous_created.html.twig')
            ->context([
                'rendezvous' => $rendezvous,
            ]);

        $this->mailer->send($email);
    }

    private function sendCanceledEmail(string $emailAddress, Rendezvous $rendezvous): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address('beellenailscare@beellenails.com', 'BeElle Nails'))
            ->to($emailAddress)
            ->subject('Annulation de votre rendez-vous - BeElle Nails')
            ->htmlTemplate('emails/rendezvous_canceled.html.twig')
            ->context([
                'rendezvous' => $rendezvous,
            ]);

        $this->mailer->send($email);
    }

    private function sendReminderEmail(string $emailAddress, Rendezvous $rendezvous): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address('beellenailscare@beellenails.com', 'BeElle Nails'))
            ->to($emailAddress)
            ->subject('Rappel de votre rendez-vous demain - BeElle Nails')
            ->htmlTemplate('emails/rendezvous_reminder.html.twig')
            ->context([
                'rendezvous' => $rendezvous,
            ]);

        $this->mailer->send($email);
    }

    private function sendUpdatedEmail(string $emailAddress, Rendezvous $rendezvous): void
    {
        // D'abord créer le template updated
        $email = (new TemplatedEmail())
            ->from(new Address('beellenailscare@beellenails.com', 'BeElle Nails'))
            ->to($emailAddress)
            ->subject('Modification de votre rendez-vous - BeElle Nails')
            ->htmlTemplate('emails/rendezvous_updated.html.twig')
            ->context([
                'rendezvous' => $rendezvous,
            ]);

        $this->mailer->send($email);
    }
}