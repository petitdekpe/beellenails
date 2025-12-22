<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Service;

use App\Entity\FormationEnrollment;
use App\Repository\FormationEnrollmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class ExpirationNotificationService
{
    public function __construct(
        private FormationEnrollmentRepository $enrollmentRepository,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger
    ) {}

    public function sendExpirationReminders(): int
    {
        $sent = 0;

        // Send reminders for enrollments expiring in 7 days
        $expiringIn7Days = $this->enrollmentRepository->findExpiringInDays(7);
        foreach ($expiringIn7Days as $enrollment) {
            if ($this->sendExpirationEmail($enrollment, 7)) {
                $enrollment->setExpirationNotifiedAt(new \DateTime());
                $sent++;
            }
        }

        // Send reminders for enrollments expiring in 3 days
        $expiringIn3Days = $this->enrollmentRepository->findExpiringInDays(3);
        foreach ($expiringIn3Days as $enrollment) {
            if ($this->sendExpirationEmail($enrollment, 3)) {
                $enrollment->setExpirationNotifiedAt(new \DateTime());
                $sent++;
            }
        }

        // Send final reminders for enrollments expiring in 1 day
        $expiringIn1Day = $this->enrollmentRepository->findExpiringInDays(1);
        foreach ($expiringIn1Day as $enrollment) {
            if ($this->sendExpirationEmail($enrollment, 1)) {
                $enrollment->setExpirationNotifiedAt(new \DateTime());
                $sent++;
            }
        }

        $this->entityManager->flush();

        return $sent;
    }

    public function markExpiredEnrollments(): int
    {
        $expiredEnrollments = $this->enrollmentRepository->findExpired();
        $count = 0;

        foreach ($expiredEnrollments as $enrollment) {
            $enrollment->setStatus('expired');
            $count++;
        }

        $this->entityManager->flush();

        return $count;
    }

    private function sendExpirationEmail(FormationEnrollment $enrollment, int $daysLeft): bool
    {
        try {
            $user = $enrollment->getUser();
            $formation = $enrollment->getFormation();

            $subject = match ($daysLeft) {
                7 => 'Votre formation expire dans 7 jours',
                3 => 'Votre formation expire dans 3 jours !',
                1 => 'URGENT : Votre formation expire demain !',
                default => 'Votre formation va bientôt expirer'
            };

            $template = match ($daysLeft) {
                7 => 'emails/formation_expiring_7_days.html.twig',
                3 => 'emails/formation_expiring_3_days.html.twig',
                1 => 'emails/formation_expiring_1_day.html.twig',
                default => 'emails/formation_expiring_generic.html.twig'
            };

            $email = (new Email())
                ->from('BeElle Nails Care <reservation@beellegroup.com>')
                ->to($user->getEmail())
                ->replyTo('reservation@beellegroup.com')
                ->subject($subject)
                ->html($this->twig->render($template, [
                    'user' => $user,
                    'enrollment' => $enrollment,
                    'formation' => $formation,
                    'daysLeft' => $daysLeft,
                    'expiresAt' => $enrollment->getExpiresAt(),
                    'progressPercentage' => $enrollment->getProgressPercentage(),
                ]));

            $email->getHeaders()
                ->addTextHeader('X-Mailer', 'BeElle Nails Booking System')
                ->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');

            $this->mailer->send($email);

            $this->logger->info('Expiration reminder sent', [
                'user_id' => $user->getId(),
                'formation_id' => $formation->getId(),
                'days_left' => $daysLeft
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send expiration reminder', [
                'enrollment_id' => $enrollment->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}