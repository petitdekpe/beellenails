<?php

namespace App\Command;

use App\Repository\PaymentRepository;
use App\Repository\RendezvousRepository;
use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-payment-data',
    description: 'Migrer les données de paiement existantes vers le nouveau format générique'
)]
class MigratePaymentDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaymentRepository $paymentRepository,
        private RendezvousRepository $rendezvousRepository,
        private FormationRepository $formationRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Migration des données de paiement vers le format générique');

        // Compter les paiements à migrer
        $paymentsToMigrate = $this->paymentRepository->createQueryBuilder('p')
            ->where('p.paymentType IS NULL')
            ->getQuery()
            ->getResult();

        $totalCount = count($paymentsToMigrate);
        
        if ($totalCount === 0) {
            $io->success('Aucun paiement à migrer. Tous les paiements sont déjà au format générique.');
            return Command::SUCCESS;
        }

        $io->info("🔍 {$totalCount} paiements trouvés à migrer");

        $migratedCount = 0;
        $errorCount = 0;
        $rendezVousCount = 0;
        $formationCount = 0;
        $orphanCount = 0;

        $io->progressStart($totalCount);

        foreach ($paymentsToMigrate as $payment) {
            try {
                $migrated = false;

                // Essayer de trouver un rendez-vous lié
                if ($payment->getUser()) {
                    $rendezvous = $this->rendezvousRepository->findOneBy([
                        'user' => $payment->getUser(),
                        'paymentReference' => $payment->getReference()
                    ]);

                    if ($rendezvous) {
                        $payment->setPaymentType('rendezvous_advance');
                        $payment->setEntityType('rendezvous');
                        $payment->setEntityId($rendezvous->getId());
                        $migrated = true;
                        $rendezVousCount++;
                    }
                }

                // Si pas trouvé, essayer avec les formations
                if (!$migrated && $payment->getUser()) {
                    $formations = $this->formationRepository->findBy(['user' => $payment->getUser()]);
                    
                    foreach ($formations as $formation) {
                        // Vérifier si la date de création du paiement correspond approximativement
                        $paymentDate = $payment->getCreatedAt();
                        $formationDate = $formation->getCreatedAt();
                        
                        if ($paymentDate && $formationDate) {
                            $diff = abs($paymentDate->getTimestamp() - $formationDate->getTimestamp());
                            
                            // Si la différence est de moins de 1 heure (3600 secondes)
                            if ($diff < 3600) {
                                $payment->setPaymentType('formation_full');
                                $payment->setEntityType('formation');
                                $payment->setEntityId($formation->getId());
                                $migrated = true;
                                $formationCount++;
                                break;
                            }
                        }
                    }
                }

                // Si toujours pas trouvé, marquer comme paiement custom orphelin
                if (!$migrated) {
                    $payment->setPaymentType('custom');
                    $payment->setEntityType('orphan');
                    $payment->setEntityId(null);
                    $orphanCount++;
                }

                $migratedCount++;
                $this->entityManager->flush();
                
            } catch (\Exception $e) {
                $errorCount++;
                $io->error("Erreur lors de la migration du paiement {$payment->getId()}: " . $e->getMessage());
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        // Afficher le résumé
        $io->newLine();
        $io->section('📊 Résumé de la migration');
        
        $io->table(
            ['Type', 'Nombre'],
            [
                ['✅ Paiements migrés avec succès', $migratedCount],
                ['🗓️  Rendez-vous', $rendezVousCount],
                ['📚 Formations', $formationCount],
                ['❓ Orphelins (custom)', $orphanCount],
                ['❌ Erreurs', $errorCount],
            ]
        );

        if ($orphanCount > 0) {
            $io->warning("⚠️  {$orphanCount} paiements n'ont pas pu être liés à une entité spécifique et ont été marqués comme 'custom/orphan'.");
            $io->note('Vous pouvez les vérifier manuellement dans le dashboard des transactions.');
        }

        if ($errorCount > 0) {
            $io->error("❌ {$errorCount} erreurs lors de la migration. Vérifiez les logs.");
            return Command::FAILURE;
        }

        $io->success('🎉 Migration terminée avec succès !');
        
        return Command::SUCCESS;
    }
}