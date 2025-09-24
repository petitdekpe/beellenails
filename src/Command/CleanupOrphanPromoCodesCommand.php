<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Command;

use App\Entity\PromoCodeUsage;
use App\Repository\PromoCodeUsageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-orphan-promo-codes',
    description: 'Nettoie les usages de codes promo sans rendez-vous associé',
)]
class CleanupOrphanPromoCodesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PromoCodeUsageRepository $usageRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Nettoyage des usages de codes promo orphelins');

        // Trouver tous les usages validés sans rendez-vous associé
        $sql = "
            SELECT pcu.id
            FROM promo_code_usage pcu
            WHERE pcu.status = 'validated' 
            AND pcu.rendezvous_id IS NULL
        ";

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $result = $stmt->executeQuery();
        $orphanIds = $result->fetchFirstColumn();

        if (empty($orphanIds)) {
            $io->success('Aucun usage orphelin trouvé !');
            return Command::SUCCESS;
        }

        $io->warning(sprintf('%d usages orphelins trouvés', count($orphanIds)));
        
        if (!$io->confirm('Voulez-vous révoquer ces usages orphelins ?', false)) {
            $io->info('Opération annulée.');
            return Command::SUCCESS;
        }

        $revokedCount = 0;

        foreach ($orphanIds as $id) {
            $usage = $this->usageRepository->find($id);
            if ($usage) {
                $usage->revoke('Usage orphelin - aucun rendez-vous associé');
                
                // Décrémenter le compteur du code promo
                $promoCode = $usage->getPromoCode();
                if ($promoCode) {
                    $promoCode->decrementUsage();
                }
                
                $revokedCount++;
                
                $io->writeln(sprintf(
                    'Révoqué: Usage #%d (Code: %s)',
                    $usage->getId(),
                    $promoCode ? $promoCode->getCode() : 'N/A'
                ));
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Nettoyage terminé. %d usages orphelins révoqués.', $revokedCount));

        return Command::SUCCESS;
    }
}