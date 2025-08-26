<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>

namespace App\Command;

use App\Entity\HomeImage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:migrate-home-images',
    description: 'Migre les images statiques de la page d\'accueil vers la base de données'
)]
class MigrateHomeImagesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $parameterBag
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Mapping des images statiques vers les types
        $staticImages = [
            // Images carrousel principal
            'hero_slide' => [
                ['file' => 'slide1.webp', 'title' => 'Slide 1', 'position' => 1],
                ['file' => 'slide2.webp', 'title' => 'Slide 2', 'position' => 2],
                ['file' => 'slide3.webp', 'title' => 'Slide 3', 'position' => 3],
            ],
            
            // Image Muriel
            'muriel' => [
                ['file' => 'muriel.webp', 'title' => 'Photo Muriel', 'position' => 1],
            ],
            
            // Images des locaux
            'local' => [
                ['file' => 'local1.webp', 'title' => 'Local - Vue 1', 'position' => 1],
                ['file' => 'local2.webp', 'title' => 'Local - Vue 2', 'position' => 2],
            ],
            
            // Image prestations
            'prestations' => [
                ['file' => 'prestations.webp', 'title' => 'Image prestations', 'position' => 1],
            ],
            
            // Image académie
            'academie' => [
                ['file' => 'academie_new.webp', 'title' => 'Image académie', 'position' => 1],
            ],
        ];

        $migratedCount = 0;
        $skippedCount = 0;

        foreach ($staticImages as $type => $images) {
            $io->section("Migration des images de type: " . $type);
            
            foreach ($images as $imageData) {
                // Vérifier si l'image existe déjà
                $existingImage = $this->entityManager
                    ->getRepository(HomeImage::class)
                    ->findOneBy([
                        'type' => $type,
                        'title' => $imageData['title']
                    ]);

                if ($existingImage) {
                    $io->note("Image '{$imageData['title']}' existe déjà, ignorée");
                    $skippedCount++;
                    continue;
                }

                // Vérifier si le fichier existe
                $projectDir = $this->parameterBag->get('kernel.project_dir');
                $sourceFile = $projectDir . '/public/assets/' . $imageData['file'];
                if (!file_exists($sourceFile)) {
                    $io->warning("Fichier source non trouvé: " . $imageData['file']);
                    continue;
                }

                // Créer le répertoire de destination s'il n'existe pas
                $destDir = $projectDir . '/public/assets/images/home';
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }

                // Copier le fichier vers le répertoire des images gérées
                $destFile = $destDir . '/' . $imageData['file'];
                if (!copy($sourceFile, $destFile)) {
                    $io->error("Impossible de copier: " . $imageData['file']);
                    continue;
                }

                // Créer l'entité HomeImage
                $homeImage = new HomeImage();
                $homeImage->setTitle($imageData['title']);
                $homeImage->setType($type);
                $homeImage->setPosition($imageData['position']);
                $homeImage->setImageName($imageData['file']);
                $homeImage->setIsActive(true);
                $homeImage->setDescription("Image migrée automatiquement depuis les assets statiques");

                $this->entityManager->persist($homeImage);
                
                $io->success("✓ Migrée: {$imageData['title']} ({$imageData['file']})");
                $migratedCount++;
            }
        }

        try {
            $this->entityManager->flush();
            $io->success("Migration terminée !");
            $io->info("Images migrées: $migratedCount");
            $io->info("Images ignorées (déjà existantes): $skippedCount");
            
            if ($migratedCount > 0) {
                $io->note("Vous pouvez maintenant gérer ces images via /dashboard/home-images/");
            }
            
        } catch (\Exception $e) {
            $io->error("Erreur lors de la sauvegarde: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}