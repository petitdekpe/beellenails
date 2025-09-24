<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:analyze-promo-codes',
    description: 'Analyse les codes promo pour identifier les incohérences',
)]
class AnalyzePromoCodesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Analyse des codes promo');

        // 1. Tous les usages validés avec leurs détails
        $sql = "
            SELECT 
                pcu.id as usage_id,
                pcu.status as usage_status,
                pcu.rendezvous_id,
                r.id as rdv_id,
                r.status as rdv_status,
                pc.code as promo_code,
                u.email as user_email
            FROM promo_code_usage pcu
            LEFT JOIN rendezvous r ON pcu.rendezvous_id = r.id  
            JOIN promo_code pc ON pcu.promo_code_id = pc.id
            JOIN user u ON pcu.user_id = u.id
            WHERE pcu.status = 'validated'
        ";

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $result = $stmt->executeQuery();
        $validatedUsages = $result->fetchAllAssociative();
        
        $io->section('Tous les usages validés:');
        $io->table(
            ['Usage ID', 'Status Usage', 'RDV ID (FK)', 'RDV ID (réel)', 'Status RDV', 'Code Promo', 'User Email'],
            $validatedUsages
        );

        // 2. Codes promo validés avec rendez-vous échoués/annulés
        $inconsistencies = array_filter($validatedUsages, function($usage) {
            return $usage['rdv_status'] && in_array($usage['rdv_status'], ['Tentative échouée', 'Échec du paiement', 'Paiement annulé', 'Annulé']);
        });

        if (empty($inconsistencies)) {
            $io->success('Aucune incohérence trouvée !');
        } else {
            $io->section('Incohérences trouvées:');
            $io->table(
                ['Usage ID', 'Status Usage', 'RDV ID', 'Status RDV', 'Code Promo', 'User Email'],
                $inconsistencies
            );
            
            $io->warning(sprintf('%d incohérences trouvées', count($inconsistencies)));
        }

        // 2. Statistiques générales
        $io->section('Statistiques générales:');
        
        $stats = [
            ['Type', 'Nombre'],
            ['Usages tentés', $this->getCount("SELECT COUNT(*) FROM promo_code_usage WHERE status = 'attempted'")],
            ['Usages validés', $this->getCount("SELECT COUNT(*) FROM promo_code_usage WHERE status = 'validated'")],
            ['Usages révoqués', $this->getCount("SELECT COUNT(*) FROM promo_code_usage WHERE status = 'revoked'")],
            ['RDV avec codes promo', $this->getCount("SELECT COUNT(*) FROM rendezvous WHERE promo_code_id IS NOT NULL")],
            ['RDV avec codes en attente', $this->getCount("SELECT COUNT(*) FROM rendezvous WHERE pending_promo_code IS NOT NULL")],
        ];
        
        $io->table(['', ''], $stats);

        return Command::SUCCESS;
    }

    private function getCount(string $sql): int
    {
        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $result = $stmt->executeQuery();
        return (int) $result->fetchOne();
    }
}