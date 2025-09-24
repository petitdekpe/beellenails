<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:update-payment-links',
    description: 'Mettre à jour les liens de paiement dans les templates pour utiliser le nouveau système générique'
)]
class UpdatePaymentLinksCommand extends Command
{
    private const TEMPLATE_DIR = __DIR__ . '/../../templates';
    
    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans modification des fichiers')
            ->addOption('backup', null, InputOption::VALUE_NONE, 'Créer une sauvegarde des fichiers modifiés');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $backup = $input->getOption('backup');
        
        $io->title('🔄 Mise à jour des liens de paiement vers le système générique');

        if ($dryRun) {
            $io->note('🔍 Mode simulation activé - aucune modification ne sera effectuée');
        }

        // Définir les remplacements à effectuer
        $replacements = [
            // Anciens liens FedaPay vers nouveaux liens génériques
            [
                'pattern' => '/path\([\'"]app_payment_fedapay[\'"],\s*\{[\'"]rendezvousId[\'"]:\s*([^}]+)\}\)/',
                'replacement' => 'path("generic_payment_init", {"provider": "fedapay", "paymentType": "rendezvous_advance", "entityType": "rendezvous", "entityId": $1})',
                'description' => 'Liens FedaPay rendez-vous vers système générique'
            ],
            [
                'pattern' => '/path\([\'"]app_payment_feexpay[\'"],\s*\{[\'"]rendezvousId[\'"]:\s*([^}]+)\}\)/',
                'replacement' => 'path("generic_payment_init", {"provider": "feexpay", "paymentType": "rendezvous_advance", "entityType": "rendezvous", "entityId": $1})',
                'description' => 'Liens FeexPay rendez-vous vers système générique'
            ],
            // Formations
            [
                'pattern' => '/path\([\'"]app_formation_payment[\'"],\s*\{[\'"]formationId[\'"]:\s*([^}]+)\}\)/',
                'replacement' => 'path("generic_payment_init", {"provider": "fedapay", "paymentType": "formation_full", "entityType": "formation", "entityId": $1})',
                'description' => 'Liens paiement formation vers système générique'
            ],
            // Liens avec variables Twig
            [
                'pattern' => '/path\([\'"]app_payment_fedapay[\'"],\s*\{[\'"]rendezvousId[\'"]:\s*([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)\}\)/',
                'replacement' => 'path("generic_payment_init", {"provider": "fedapay", "paymentType": "rendezvous_advance", "entityType": "rendezvous", "entityId": $1})',
                'description' => 'Variables Twig FedaPay rendez-vous'
            ],
            [
                'pattern' => '/path\([\'"]app_payment_feexpay[\'"],\s*\{[\'"]rendezvousId[\'"]:\s*([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)\}\)/',
                'replacement' => 'path("generic_payment_init", {"provider": "feexpay", "paymentType": "rendezvous_advance", "entityType": "rendezvous", "entityId": $1})',
                'description' => 'Variables Twig FeexPay rendez-vous'
            ]
        ];

        // Trouver tous les fichiers Twig
        $finder = new Finder();
        $finder->files()
            ->in(self::TEMPLATE_DIR)
            ->name('*.html.twig')
            ->name('*.twig');

        $totalFiles = 0;
        $modifiedFiles = 0;
        $totalReplacements = 0;

        foreach ($finder as $file) {
            $totalFiles++;
            $filePath = $file->getRealPath();
            $content = file_get_contents($filePath);
            $originalContent = $content;
            $fileReplacements = 0;

            // Appliquer les remplacements
            foreach ($replacements as $replacement) {
                $newContent = preg_replace(
                    $replacement['pattern'], 
                    $replacement['replacement'], 
                    $content,
                    -1,
                    $count
                );
                
                if ($count > 0) {
                    $content = $newContent;
                    $fileReplacements += $count;
                    $totalReplacements += $count;
                    
                    if ($io->isVerbose()) {
                        $io->text("  - {$replacement['description']}: {$count} remplacement(s)");
                    }
                }
            }

            // Si des modifications ont été faites
            if ($content !== $originalContent) {
                $modifiedFiles++;
                $relativePath = str_replace(realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR, '', $filePath);
                
                $io->text("📝 {$relativePath} ({$fileReplacements} modifications)");

                if (!$dryRun) {
                    // Créer une sauvegarde si demandé
                    if ($backup) {
                        $backupPath = $filePath . '.backup.' . date('Y-m-d_H-i-s');
                        copy($filePath, $backupPath);
                        if ($io->isVerbose()) {
                            $io->text("  💾 Sauvegarde: {$backupPath}");
                        }
                    }

                    // Écrire le nouveau contenu
                    file_put_contents($filePath, $content);
                }
            }
        }

        // Résumé
        $io->newLine();
        $io->section('📊 Résumé');
        
        $io->table(
            ['Métrique', 'Valeur'],
            [
                ['📂 Fichiers analysés', $totalFiles],
                ['📝 Fichiers modifiés', $modifiedFiles],
                ['🔄 Remplacements totaux', $totalReplacements],
            ]
        );

        if ($totalReplacements === 0) {
            $io->success('✅ Aucun lien à mettre à jour trouvé. Tous les templates semblent déjà utiliser le système générique.');
        } elseif ($dryRun) {
            $io->warning("🔍 Mode simulation: {$totalReplacements} remplacements seraient effectués dans {$modifiedFiles} fichier(s)");
            $io->note('Relancez sans --dry-run pour appliquer les modifications');
        } else {
            $io->success("🎉 {$totalReplacements} liens mis à jour avec succès dans {$modifiedFiles} fichier(s)!");
            
            if ($backup) {
                $io->note('💾 Les sauvegardes ont été créées avec l\'extension .backup.[timestamp]');
            }
        }

        // Suggestions additionnelles
        $io->section('💡 Suggestions');
        $io->text([
            '1. Vérifiez que les nouveaux liens fonctionnent correctement',
            '2. Testez les paiements FedaPay et FeexPay',
            '3. Vérifiez les redirections et webhooks',
            '4. Pensez à supprimer les anciennes routes si elles ne sont plus utilisées'
        ]);

        return Command::SUCCESS;
    }
}