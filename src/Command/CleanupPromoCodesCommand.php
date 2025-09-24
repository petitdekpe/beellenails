<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Command;

use App\Entity\PromoCodeUsage;
use App\Repository\PromoCodeUsageRepository;
use App\Repository\RendezvousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-promo-codes',
    description: 'Nettoie les codes promo incohérents (validés avec rendez-vous échoués/annulés)',
)]
class CleanupPromoCodesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PromoCodeUsageRepository $usageRepository,
        private RendezvousRepository $rendezvousRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Nettoyage des codes promo incohérents');

        // Trouver tous les usages validés
        $validatedUsages = $this->usageRepository->findBy([
            'status' => PromoCodeUsage::STATUS_VALIDATED
        ]);

        $io->progressStart(count($validatedUsages));

        $revokedCount = 0;
        $statusToRevoke = [
            'Tentative échouée',
            'Échec du paiement', 
            'Paiement annulé',
            'Annulé'
        ];

        foreach ($validatedUsages as $usage) {
            $rendezvous = $usage->getRendezvous();
            
            if ($rendezvous && in_array($rendezvous->getStatus(), $statusToRevoke)) {
                $usage->revoke('Nettoyage automatique - RDV en statut: ' . $rendezvous->getStatus());
                
                // Décrémenter le compteur du code promo
                $promoCode = $usage->getPromoCode();
                if ($promoCode) {
                    $promoCode->decrementUsage();
                }
                
                $revokedCount++;
                
                $io->writeln(sprintf(
                    'Révoqué: Usage #%d (RDV #%d en statut "%s")',
                    $usage->getId(),
                    $rendezvous->getId(),
                    $rendezvous->getStatus()
                ));
            }
            
            $io->progressAdvance();
        }

        $this->entityManager->flush();
        $io->progressFinish();

        $io->success(sprintf('Nettoyage terminé. %d codes promo révoqués.', $revokedCount));

        return Command::SUCCESS;
    }
}