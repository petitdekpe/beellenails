<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Service;

use App\Entity\Formation;
use App\Entity\FormationEnrollment;
use App\Entity\Payment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class FormationPaymentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger
    ) {}

    public function createFormationEnrollment(User $user, Formation $formation, string $accessType = '30_days'): FormationEnrollment
    {
        // Vérifier si l'utilisateur n'est pas déjà inscrit à cette formation
        $existingEnrollment = $this->entityManager->getRepository(FormationEnrollment::class)
            ->findOneBy([
                'user' => $user,
                'formation' => $formation,
                'status' => ['active', 'completed']
            ]);

        if ($existingEnrollment) {
            throw new \Exception('L\'utilisateur est déjà inscrit à cette formation.');
        }

        $enrollment = new FormationEnrollment();
        $enrollment->setUser($user);
        $enrollment->setFormation($formation);
        $enrollment->setStatus('active');
        $enrollment->setEnrolledAt(new \DateTime());
        $enrollment->setProgressPercentage(0);
        $enrollment->setTotalTimeSpent(0);

        // Calculer la date d'expiration selon le type d'accès
        $expiresAt = $this->calculateExpirationDate($accessType);
        if ($expiresAt) {
            $enrollment->setExpiresAt($expiresAt);
        }

        $this->entityManager->persist($enrollment);
        $this->entityManager->flush();

        // Créer les progressions des modules
        $this->initializeModuleProgress($enrollment);

        return $enrollment;
    }

    public function processFormationPayment(
        User $user, 
        Formation $formation, 
        float $amount, 
        string $provider, 
        string $transactionId,
        array $paymentData = [],
        string $accessType = '30_days'
    ): array {
        try {
            $this->entityManager->beginTransaction();

            // Créer l'inscription
            $enrollment = $this->createFormationEnrollment($user, $formation, $accessType);

            // Créer l'enregistrement de paiement
            $payment = new Payment();
            $payment->setAmount($amount);
            $payment->setProvider($provider);
            $payment->setTransactionId($transactionId);
            $payment->setStatus('completed');
            $payment->setCreatedAt(new \DateTime());
            $payment->setCustomer($user);
            
            // Associer les données spécifiques selon le provider
            switch ($provider) {
                case 'fedapay':
                    $payment->setFedapayId($paymentData['fedapay_id'] ?? null);
                    break;
                case 'feexpay':
                    $payment->setFeexpayId($paymentData['feexpay_id'] ?? null);
                    break;
                case 'celtiis':
                    $payment->setCeltiisId($paymentData['celtiis_id'] ?? null);
                    break;
            }

            $this->entityManager->persist($payment);
            $this->entityManager->flush();
            $this->entityManager->commit();

            // Envoyer l'email de confirmation
            $this->sendEnrollmentConfirmationEmail($enrollment);

            $this->logger->info('Formation payment processed successfully', [
                'user_id' => $user->getId(),
                'formation_id' => $formation->getId(),
                'payment_id' => $payment->getId(),
                'provider' => $provider,
                'amount' => $amount
            ]);

            return [
                'success' => true,
                'enrollment' => $enrollment,
                'payment' => $payment,
                'message' => 'Paiement traité avec succès et inscription créée.'
            ];

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            $this->logger->error('Formation payment processing failed', [
                'user_id' => $user->getId(),
                'formation_id' => $formation->getId(),
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors du traitement du paiement : ' . $e->getMessage()
            ];
        }
    }

    public function processFedapayWebhook(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);
        
        if (!$payload || !isset($payload['entity']['id'])) {
            throw new \InvalidArgumentException('Payload FedaPay invalide');
        }

        $transactionId = $payload['entity']['id'];
        $status = $payload['entity']['status'] ?? '';
        $amount = $payload['entity']['amount'] ?? 0;

        // Récupérer le paiement en base
        $payment = $this->entityManager->getRepository(Payment::class)
            ->findOneBy(['fedapayId' => $transactionId]);

        if (!$payment) {
            throw new \Exception('Paiement FedaPay non trouvé : ' . $transactionId);
        }

        // Mettre à jour le statut
        if ($status === 'approved') {
            $payment->setStatus('completed');
            $this->activateEnrollmentFromPayment($payment);
        } elseif ($status === 'declined' || $status === 'canceled') {
            $payment->setStatus('failed');
        }

        $this->entityManager->flush();

        return ['success' => true, 'status' => $status];
    }

    public function processFeexpayWebhook(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);
        
        if (!$payload || !isset($payload['transaction_id'])) {
            throw new \InvalidArgumentException('Payload Feexpay invalide');
        }

        $transactionId = $payload['transaction_id'];
        $status = $payload['status'] ?? '';
        $amount = $payload['amount'] ?? 0;

        // Récupérer le paiement en base
        $payment = $this->entityManager->getRepository(Payment::class)
            ->findOneBy(['feexpayId' => $transactionId]);

        if (!$payment) {
            throw new \Exception('Paiement Feexpay non trouvé : ' . $transactionId);
        }

        // Mettre à jour le statut
        if ($status === 'success' || $status === 'completed') {
            $payment->setStatus('completed');
            $this->activateEnrollmentFromPayment($payment);
        } elseif ($status === 'failed' || $status === 'canceled') {
            $payment->setStatus('failed');
        }

        $this->entityManager->flush();

        return ['success' => true, 'status' => $status];
    }

    public function processCeltiisWebhook(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);
        
        if (!$payload || !isset($payload['payment_id'])) {
            throw new \InvalidArgumentException('Payload Celtiis invalide');
        }

        $transactionId = $payload['payment_id'];
        $status = $payload['payment_status'] ?? '';
        $amount = $payload['amount'] ?? 0;

        // Récupérer le paiement en base
        $payment = $this->entityManager->getRepository(Payment::class)
            ->findOneBy(['celtiisId' => $transactionId]);

        if (!$payment) {
            throw new \Exception('Paiement Celtiis non trouvé : ' . $transactionId);
        }

        // Mettre à jour le statut
        if ($status === 'paid' || $status === 'confirmed') {
            $payment->setStatus('completed');
            $this->activateEnrollmentFromPayment($payment);
        } elseif ($status === 'failed' || $status === 'cancelled') {
            $payment->setStatus('failed');
        }

        $this->entityManager->flush();

        return ['success' => true, 'status' => $status];
    }

    private function calculateExpirationDate(string $accessType): ?\DateTime
    {
        $now = new \DateTime();
        
        return match($accessType) {
            '7_days' => $now->add(new \DateInterval('P7D')),
            '14_days' => $now->add(new \DateInterval('P14D')),
            '30_days' => $now->add(new \DateInterval('P30D')),
            '60_days' => $now->add(new \DateInterval('P60D')),
            '90_days' => $now->add(new \DateInterval('P90D')),
            '6_months' => $now->add(new \DateInterval('P6M')),
            '1_year' => $now->add(new \DateInterval('P1Y')),
            'lifetime' => null,
            default => $now->add(new \DateInterval('P30D'))
        };
    }

    private function initializeModuleProgress(FormationEnrollment $enrollment): void
    {
        $modules = $enrollment->getFormation()->getModules();
        
        foreach ($modules as $module) {
            $moduleProgress = new \App\Entity\ModuleProgress();
            $moduleProgress->setEnrollment($enrollment);
            $moduleProgress->setModule($module);
            $moduleProgress->setStarted(false);
            $moduleProgress->setCompleted(false);
            $moduleProgress->setCompletionPercentage(0);
            $moduleProgress->setTimeSpent(0);
            $moduleProgress->setVideoPosition(0);

            $this->entityManager->persist($moduleProgress);
        }

        $this->entityManager->flush();
    }

    private function activateEnrollmentFromPayment(Payment $payment): void
    {
        // Cette méthode pourrait être étendue pour activer automatiquement
        // l'inscription une fois le paiement confirmé
        $this->logger->info('Payment confirmed, enrollment should be activated', [
            'payment_id' => $payment->getId(),
            'transaction_id' => $payment->getTransactionId()
        ]);
    }

    private function sendEnrollmentConfirmationEmail(FormationEnrollment $enrollment): void
    {
        try {
            $user = $enrollment->getUser();
            $formation = $enrollment->getFormation();

            $email = (new Email())
                ->from('noreply@beellenails.com')
                ->to($user->getEmail())
                ->subject('Confirmation d\'inscription - ' . $formation->getNom())
                ->html($this->twig->render('emails/formation_enrollment_confirmation.html.twig', [
                    'user' => $user,
                    'enrollment' => $enrollment,
                    'formation' => $formation,
                ]));

            $this->mailer->send($email);

            $this->logger->info('Enrollment confirmation email sent', [
                'user_id' => $user->getId(),
                'formation_id' => $formation->getId(),
                'enrollment_id' => $enrollment->getId()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send enrollment confirmation email', [
                'enrollment_id' => $enrollment->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getPaymentStats(?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $qb = $this->entityManager->getRepository(Payment::class)->createQueryBuilder('p');
        
        if ($startDate) {
            $qb->andWhere('p.createdAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }
        
        if ($endDate) {
            $qb->andWhere('p.createdAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $payments = $qb->getQuery()->getResult();

        $stats = [
            'total_amount' => 0,
            'total_transactions' => count($payments),
            'by_provider' => [
                'fedapay' => ['count' => 0, 'amount' => 0],
                'feexpay' => ['count' => 0, 'amount' => 0],
                'celtiis' => ['count' => 0, 'amount' => 0],
            ],
            'by_status' => [
                'completed' => ['count' => 0, 'amount' => 0],
                'pending' => ['count' => 0, 'amount' => 0],
                'failed' => ['count' => 0, 'amount' => 0],
            ]
        ];

        foreach ($payments as $payment) {
            $stats['total_amount'] += $payment->getAmount();
            
            // Par provider
            $provider = $payment->getProvider();
            if (isset($stats['by_provider'][$provider])) {
                $stats['by_provider'][$provider]['count']++;
                $stats['by_provider'][$provider]['amount'] += $payment->getAmount();
            }
            
            // Par statut
            $status = $payment->getStatus();
            if (isset($stats['by_status'][$status])) {
                $stats['by_status'][$status]['count']++;
                $stats['by_status'][$status]['amount'] += $payment->getAmount();
            }
        }

        return $stats;
    }
}