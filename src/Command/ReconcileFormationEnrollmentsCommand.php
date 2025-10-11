<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Command;

use App\Entity\Payment;
use App\Entity\Formation;
use App\Entity\FormationEnrollment;
use App\Entity\ModuleProgress;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reconcile-formation-enrollments',
    description: 'Réconcilie les paiements de formations avec les inscriptions manquantes'
)]
class ReconcileFormationEnrollmentsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Afficher les actions sans les exécuter')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Forcer la création même si une inscription existe déjà')
            ->setHelp('Cette commande analyse tous les paiements réussis pour les formations et crée les inscriptions manquantes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');
        $isForce = $input->getOption('force');

        $io->title('🔄 Réconciliation des inscriptions aux formations');

        if ($isDryRun) {
            $io->note('Mode DRY-RUN activé - Aucune modification ne sera apportée');
        }

        try {
            // Récupérer tous les paiements réussis pour les formations
            $successfulFormationPayments = $this->entityManager
                ->getRepository(Payment::class)
                ->createQueryBuilder('p')
                ->where('p.entityType = :entityType')
                ->andWhere('p.status IN (:statuses)')
                ->setParameter('entityType', 'formation')
                ->setParameter('statuses', ['approved', 'successful'])
                ->getQuery()
                ->getResult();

            $io->info(sprintf('✅ Trouvé %d paiements réussis pour des formations', count($successfulFormationPayments)));

            $enrollmentsCreated = 0;
            $enrollmentsReactivated = 0;
            $enrollmentsSkipped = 0;
            $errorsCount = 0;

            foreach ($successfulFormationPayments as $payment) {
                $io->writeln(sprintf('📋 Traitement du paiement #%d (Ref: %s)', $payment->getId(), $payment->getReference()));

                try {
                    // Récupérer la formation
                    $formation = $this->entityManager
                        ->getRepository(Formation::class)
                        ->find($payment->getEntityId());

                    if (!$formation) {
                        $io->warning(sprintf('❌ Formation avec ID %d introuvable pour le paiement #%d', $payment->getEntityId(), $payment->getId()));
                        $errorsCount++;
                        continue;
                    }

                    // Récupérer l'utilisateur
                    $user = $payment->getCustomer();
                    if (!$user) {
                        $io->warning(sprintf('❌ Utilisateur introuvable pour le paiement #%d', $payment->getId()));
                        $errorsCount++;
                        continue;
                    }

                    $io->writeln(sprintf('   👤 Utilisateur: %s (%s)', $user->__toString(), $user->getEmail()));
                    $io->writeln(sprintf('   📚 Formation: %s', $formation->getNom()));

                    // Vérifier si une inscription existe déjà
                    $existingEnrollment = $this->entityManager
                        ->getRepository(FormationEnrollment::class)
                        ->findOneBy([
                            'user' => $user,
                            'formation' => $formation
                        ]);

                    if ($existingEnrollment) {
                        if ($existingEnrollment->getStatus() === 'active') {
                            $io->writeln('   ✅ Inscription active déjà existante - ignorée');
                            $enrollmentsSkipped++;
                            continue;
                        } elseif (in_array($existingEnrollment->getStatus(), ['expired', 'cancelled']) && !$isForce) {
                            if (!$isDryRun) {
                                $existingEnrollment->setStatus('active');
                                $existingEnrollment->setEnrolledAt(new \DateTime());
                                $existingEnrollment->setLastAccessedAt(new \DateTime());
                                $this->entityManager->persist($existingEnrollment);
                            }
                            $io->writeln('   🔄 Inscription réactivée');
                            $enrollmentsReactivated++;
                        } else {
                            $io->writeln('   ⏭️ Inscription existante - ignorée (utilisez --force pour forcer)');
                            $enrollmentsSkipped++;
                            continue;
                        }
                    } else {
                        // Créer une nouvelle inscription
                        if (!$isDryRun) {
                            $enrollment = new FormationEnrollment();
                            $enrollment->setUser($user);
                            $enrollment->setFormation($formation);
                            $enrollment->setStatus('active');
                            $enrollment->setEnrolledAt($payment->getCreatedAt() ? \DateTime::createFromImmutable($payment->getCreatedAt()) : new \DateTime());
                            $enrollment->setLastAccessedAt(new \DateTime());

                            $this->entityManager->persist($enrollment);

                            // Créer les progrès de modules pour tous les modules actifs
                            $moduleCount = 0;
                            foreach ($formation->getModules() as $module) {
                                if ($module->isActive()) {
                                    $moduleProgress = new ModuleProgress();
                                    $moduleProgress->setEnrollment($enrollment);
                                    $moduleProgress->setModule($module);
                                    $this->entityManager->persist($moduleProgress);
                                    $moduleCount++;
                                }
                            }

                            $this->logger->info('[Reconciliation] Created enrollment from payment', [
                                'enrollment_id' => $enrollment->getId(),
                                'user_id' => $user->getId(),
                                'formation_id' => $formation->getId(),
                                'payment_id' => $payment->getId(),
                                'modules_created' => $moduleCount
                            ]);

                            $io->writeln(sprintf('   ✨ Nouvelle inscription créée avec %d modules', $moduleCount));
                        } else {
                            $io->writeln('   ✨ Nouvelle inscription serait créée');
                        }
                        $enrollmentsCreated++;
                    }

                } catch (\Exception $e) {
                    $io->error(sprintf('❌ Erreur lors du traitement du paiement #%d: %s', $payment->getId(), $e->getMessage()));
                    $this->logger->error('[Reconciliation] Error processing payment', [
                        'payment_id' => $payment->getId(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $errorsCount++;
                }
            }

            // Sauvegarder les modifications
            if (!$isDryRun && ($enrollmentsCreated > 0 || $enrollmentsReactivated > 0)) {
                $this->entityManager->flush();
                $io->success('💾 Modifications sauvegardées en base de données');
            }

            // Résumé
            $io->section('📊 Résumé de la réconciliation');
            $io->table(['Action', 'Nombre'], [
                ['Inscriptions créées', $enrollmentsCreated],
                ['Inscriptions réactivées', $enrollmentsReactivated],
                ['Inscriptions ignorées', $enrollmentsSkipped],
                ['Erreurs', $errorsCount]
            ]);

            if ($isDryRun) {
                $io->note('🔍 Aucune modification apportée (mode dry-run). Utilisez la commande sans --dry-run pour appliquer les changements.');
            } else {
                $io->success(sprintf('✅ Réconciliation terminée avec succès: %d inscriptions créées, %d réactivées', $enrollmentsCreated, $enrollmentsReactivated));
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('❌ Erreur fatale lors de la réconciliation: ' . $e->getMessage());
            $this->logger->error('[Reconciliation] Fatal error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}