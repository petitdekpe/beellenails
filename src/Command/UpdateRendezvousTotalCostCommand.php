<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Command;

use App\Entity\Rendezvous;
use App\Repository\RendezvousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-rendezvous-total-cost',
    description: 'Met à jour le coût total de tous les rendez-vous existants dans la base de données'
)]
class UpdateRendezvousTotalCostCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RendezvousRepository $rendezvousRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Afficher les modifications sans les appliquer')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limiter le nombre de rendez-vous à traiter', 0)
            ->setHelp('
Cette commande calcule et met à jour le coût total pour tous les rendez-vous existants.
Le coût total est calculé en additionnant le prix de la prestation principale et tous les suppléments.

Exemples:
  # Mise à jour en mode test (dry-run)
  php bin/console app:update-rendezvous-total-cost --dry-run
  
  # Mise à jour réelle de tous les rendez-vous
  php bin/console app:update-rendezvous-total-cost
  
  # Mise à jour des 10 premiers rendez-vous seulement
  php bin/console app:update-rendezvous-total-cost --limit=10
');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');
        $limit = (int) $input->getOption('limit');

        $io->title('Mise à jour des coûts totaux des rendez-vous');

        if ($isDryRun) {
            $io->note('Mode DRY-RUN activé - Aucune modification ne sera appliquée');
        }

        // Récupérer les rendez-vous sans coût total ou avec un coût total null
        $queryBuilder = $this->rendezvousRepository->createQueryBuilder('r')
            ->where('r.totalCost IS NULL')
            ->orderBy('r.id', 'ASC');

        if ($limit > 0) {
            $queryBuilder->setMaxResults($limit);
            $io->info("Limitation à {$limit} rendez-vous");
        }

        $rendezvousList = $queryBuilder->getQuery()->getResult();

        if (empty($rendezvousList)) {
            $io->success('Tous les rendez-vous ont déjà un coût total calculé !');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Trouvé %d rendez-vous à traiter', count($rendezvousList)));

        $io->progressStart(count($rendezvousList));

        $updatedCount = 0;
        $errorCount = 0;

        foreach ($rendezvousList as $rendezvous) {
            try {
                $oldCost = $rendezvous->getTotalCost();
                $newCost = $rendezvous->calculateTotalCost();

                // Affichage des détails pour chaque rendez-vous
                if ($output->isVerbose()) {
                    $io->text(sprintf(
                        'RDV #%d - %s le %s à %s - Prestation: %s (%s FCFA) - Suppléments: %d - Total: %s FCFA',
                        $rendezvous->getId(),
                        $rendezvous->getUser()?->getFullName() ?? 'Utilisateur inconnu',
                        $rendezvous->getDay()?->format('d/m/Y') ?? 'Date inconnue',
                        $rendezvous->getCreneau()?->getStartTime()?->format('H:i') ?? 'Heure inconnue',
                        $rendezvous->getPrestation()?->getTitle() ?? 'Prestation inconnue',
                        $rendezvous->getPrestation()?->getPrice() ?? '0',
                        $rendezvous->getSupplement()->count(),
                        $newCost
                    ));
                }

                if (!$isDryRun) {
                    $rendezvous->setTotalCost($newCost);
                    $this->entityManager->persist($rendezvous);
                }

                $updatedCount++;
            } catch (\Exception $e) {
                $io->error(sprintf('Erreur pour le rendez-vous #%d: %s', $rendezvous->getId(), $e->getMessage()));
                $errorCount++;
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        if (!$isDryRun && $updatedCount > 0) {
            $this->entityManager->flush();
            $io->success(sprintf('Base de données mise à jour avec %d rendez-vous', $updatedCount));
        }

        // Résumé des résultats
        $io->section('Résumé');
        $io->table(
            ['Statistique', 'Valeur'],
            [
                ['Rendez-vous traités', $updatedCount],
                ['Erreurs rencontrées', $errorCount],
                ['Mode', $isDryRun ? 'DRY-RUN (test)' : 'REAL (mise à jour effectuée)']
            ]
        );

        if ($isDryRun) {
            $io->note('Pour appliquer les modifications, exécutez la commande sans --dry-run');
        }

        return Command::SUCCESS;
    }
}