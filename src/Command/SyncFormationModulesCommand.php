<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Command;

use App\Entity\FormationEnrollment;
use App\Entity\ModuleProgress;
use App\Repository\FormationEnrollmentRepository;
use App\Repository\ModuleProgressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-formation-modules',
    description: 'Synchronise les ModuleProgress manquants pour les inscriptions existantes'
)]
class SyncFormationModulesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FormationEnrollmentRepository $enrollmentRepository,
        private ModuleProgressRepository $moduleProgressRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Synchronisation des modules de formation');

        // Récupérer toutes les inscriptions actives
        $enrollments = $this->enrollmentRepository->findAll();

        $totalCreated = 0;
        $totalEnrollments = count($enrollments);

        $io->progressStart($totalEnrollments);

        foreach ($enrollments as $enrollment) {
            $formation = $enrollment->getFormation();
            $createdForThisEnrollment = 0;

            foreach ($formation->getModules() as $module) {
                // Vérifier si le module est actif
                if (!$module->isActive()) {
                    continue;
                }

                // Vérifier si un ModuleProgress existe déjà
                $existingProgress = $this->moduleProgressRepository->findOneBy([
                    'enrollment' => $enrollment,
                    'module' => $module
                ]);

                // Si pas de progress, on le crée
                if (!$existingProgress) {
                    $moduleProgress = new ModuleProgress();
                    $moduleProgress->setEnrollment($enrollment);
                    $moduleProgress->setModule($module);
                    $this->entityManager->persist($moduleProgress);

                    $createdForThisEnrollment++;
                    $totalCreated++;
                }
            }

            if ($createdForThisEnrollment > 0) {
                $io->writeln(sprintf(
                    '  [%s] %d module(s) créé(s) pour %s - Formation: %s',
                    date('H:i:s'),
                    $createdForThisEnrollment,
                    $enrollment->getUser()->getEmail(),
                    $formation->getNom()
                ));
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        // Sauvegarder tous les changements
        $this->entityManager->flush();

        $io->success(sprintf(
            'Synchronisation terminée ! %d ModuleProgress créé(s) pour %d inscription(s).',
            $totalCreated,
            $totalEnrollments
        ));

        return Command::SUCCESS;
    }
}
