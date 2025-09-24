<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Command;

use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-formations',
    description: 'Fix existing formations with default values for new fields',
)]
class FixFormationsCommand extends Command
{
    public function __construct(
        private FormationRepository $formationRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Fixing existing formations with default values');

        // Get all formations
        $formations = $this->formationRepository->findAll();
        $io->progressStart(count($formations));

        foreach ($formations as $formation) {
            // Set default values for new fields if they are null
            if ($formation->isFree() === null) {
                // If cost is 0, mark as free, otherwise as paid
                $formation->setIsFree($formation->getCout() == 0);
            }

            if ($formation->getAccessType() === null) {
                $formation->setAccessType('relative');
            }

            if ($formation->isActive() === null) {
                $formation->setIsActive(true);
            }

            if ($formation->getCreatedAt() === null) {
                $formation->setCreatedAt(new \DateTime());
            }

            // Set instructor name if not set
            if (!$formation->getInstructorName()) {
                $formation->setInstructorName('Muriel AHODODE Epse ASSOGBA');
            }

            // Set instructor bio if not set
            if (!$formation->getInstructorBio()) {
                $formation->setInstructorBio('Propriétaire de l\'institut et prothésiste ongulaire spécialisé dans le traitement des ongles naturels. Formée aux quatre coins du monde auprès des formateurs les plus performants.');
            }

            // Set theme based on formation name
            if (!$formation->getTheme()) {
                $nom = strtolower($formation->getNom());
                if (strpos($nom, 'russe') !== false) {
                    $formation->setTheme('techniques_avancees');
                } elseif (strpos($nom, 'renforcement') !== false) {
                    $formation->setTheme('soins_ongles');
                } elseif (strpos($nom, 'gel') !== false || strpos($nom, 'acrygel') !== false) {
                    $formation->setTheme('prothesie_ongulaire');
                } else {
                    $formation->setTheme('formation_complete');
                }
            }

            // Set level based on formation content
            if (!$formation->getLevel()) {
                $nom = strtolower($formation->getNom());
                if (strpos($nom, 'russe') !== false || strpos($nom, 'acrygel') !== false) {
                    $formation->setLevel('avance');
                } elseif (strpos($nom, 'renforcement') !== false) {
                    $formation->setLevel('intermediaire');
                } else {
                    $formation->setLevel('debutant');
                }
            }

            // Set default duration (estimate based on complexity)
            if (!$formation->getDuration()) {
                if (strpos(strtolower($formation->getNom()), 'russe') !== false) {
                    $formation->setDuration(480); // 8 hours
                } else {
                    $formation->setDuration(360); // 6 hours
                }
            }

            $this->entityManager->persist($formation);
            $io->progressAdvance();
        }

        $this->entityManager->flush();
        $io->progressFinish();

        $io->success(sprintf('Successfully updated %d formations with default values.', count($formations)));

        return Command::SUCCESS;
    }
}