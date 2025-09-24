<?php

namespace App\Command;

use App\Repository\PaymentRepository;
use App\Repository\PaymentConfigurationRepository;
use App\Service\PaymentTypeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:validate-payment-system',
    description: 'Valider l\'intégrité du nouveau système de paiement générique'
)]
class ValidatePaymentSystemCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaymentRepository $paymentRepository,
        private PaymentConfigurationRepository $paymentConfigRepository,
        private PaymentTypeResolver $paymentTypeResolver
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('fix', null, InputOption::VALUE_NONE, 'Corriger automatiquement les problèmes détectés')
            ->addOption('detailed', null, InputOption::VALUE_NONE, 'Afficher les détails complets');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fix = $input->getOption('fix');
        $detailed = $input->getOption('detailed');
        
        $io->title('🔍 Validation du système de paiement générique');

        $issues = [];
        $fixedCount = 0;

        // 1. Vérifier les configurations de paiement
        $io->section('1. Vérification des configurations de paiement');
        
        $configs = $this->paymentConfigRepository->findAll();
        $configsByType = [];
        
        foreach ($configs as $config) {
            $configsByType[$config->getType()] = $config;
        }
        
        $requiredTypes = ['rendezvous_advance', 'formation_full', 'formation_advance'];
        $missingConfigs = [];
        
        foreach ($requiredTypes as $type) {
            if (!isset($configsByType[$type])) {
                $missingConfigs[] = $type;
            }
        }
        
        if (!empty($missingConfigs)) {
            $issues[] = "⚠️  Configurations manquantes: " . implode(', ', $missingConfigs);
            
            if ($fix) {
                $io->info('🔧 Création des configurations manquantes...');
                $this->createMissingConfigs($missingConfigs);
                $fixedCount += count($missingConfigs);
            }
        } else {
            $io->success('✅ Toutes les configurations requises sont présentes');
        }

        // 2. Vérifier les paiements
        $io->section('2. Vérification des paiements');
        
        $allPayments = $this->paymentRepository->findAll();
        $paymentIssues = [
            'missing_type' => [],
            'missing_entity' => [],
            'invalid_entity' => [],
            'orphan_payments' => []
        ];
        
        foreach ($allPayments as $payment) {
            // Vérifier paymentType
            if (!$payment->getPaymentType()) {
                $paymentIssues['missing_type'][] = $payment;
            }
            
            // Vérifier entityType et entityId
            if (!$payment->getEntityType()) {
                $paymentIssues['missing_entity'][] = $payment;
            } elseif ($payment->getEntityType() !== 'orphan' && $payment->getEntityId()) {
                // Vérifier que l'entité existe vraiment
                try {
                    $entity = $this->paymentTypeResolver->resolveEntity(
                        $payment->getEntityType(),
                        $payment->getEntityId()
                    );
                    if (!$entity) {
                        $paymentIssues['invalid_entity'][] = $payment;
                    }
                } catch (\Exception $e) {
                    $paymentIssues['invalid_entity'][] = $payment;
                }
            } elseif ($payment->getEntityType() === 'orphan') {
                $paymentIssues['orphan_payments'][] = $payment;
            }
        }

        // Afficher les résultats
        $totalPayments = count($allPayments);
        $validPayments = $totalPayments - array_sum(array_map('count', $paymentIssues));
        
        $io->table(
            ['Statut', 'Nombre'],
            [
                ['✅ Paiements valides', $validPayments],
                ['❌ Type manquant', count($paymentIssues['missing_type'])],
                ['❌ Entité manquante', count($paymentIssues['missing_entity'])],
                ['❌ Entité invalide', count($paymentIssues['invalid_entity'])],
                ['⚠️  Paiements orphelins', count($paymentIssues['orphan_payments'])],
                ['📊 Total', $totalPayments],
            ]
        );

        // Détails si demandé
        if ($detailed) {
            foreach ($paymentIssues as $issueType => $payments) {
                if (!empty($payments)) {
                    $io->section(ucfirst(str_replace('_', ' ', $issueType)));
                    foreach ($payments as $payment) {
                        $io->text("- Paiement #{$payment->getId()} ({$payment->getReference()})");
                    }
                }
            }
        }

        // 3. Vérifier les montants de configuration
        $io->section('3. Vérification des montants');
        
        $zeroAmountConfigs = [];
        foreach ($configs as $config) {
            if ($config->getAmount() <= 0 && $config->getType() !== 'custom') {
                $zeroAmountConfigs[] = $config;
            }
        }
        
        if (!empty($zeroAmountConfigs)) {
            $issues[] = "⚠️  " . count($zeroAmountConfigs) . " configuration(s) avec montant nul ou négatif";
            
            if ($detailed) {
                foreach ($zeroAmountConfigs as $config) {
                    $io->text("- {$config->getLabel()} ({$config->getType()}): {$config->getAmount()}");
                }
            }
        } else {
            $io->success('✅ Tous les montants de configuration sont valides');
        }

        // 4. Résumé final
        $io->section('📊 Résumé de la validation');
        
        $totalIssues = count($issues) + array_sum(array_map('count', $paymentIssues));
        
        if ($totalIssues === 0) {
            $io->success('🎉 Aucun problème détecté ! Le système de paiement générique est fonctionnel.');
            return Command::SUCCESS;
        }
        
        $io->warning("⚠️  {$totalIssues} problème(s) détecté(s)");
        
        foreach ($issues as $issue) {
            $io->text($issue);
        }
        
        if ($fix && $fixedCount > 0) {
            $io->success("🔧 {$fixedCount} problème(s) corrigé(s) automatiquement");
        } elseif (!$fix && $totalIssues > 0) {
            $io->note('💡 Utilisez --fix pour corriger automatiquement les problèmes simples');
        }
        
        return $totalIssues > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function createMissingConfigs(array $missingTypes): void
    {
        $defaultConfigs = [
            'rendezvous_advance' => [
                'label' => 'Acompte pour rendez-vous',
                'amount' => 5000,
                'description' => 'Montant de l\'acompte demandé lors de la réservation d\'un rendez-vous'
            ],
            'formation_full' => [
                'label' => 'Formation complète',
                'amount' => 25000,
                'description' => 'Prix complet pour l\'accès à une formation'
            ],
            'formation_advance' => [
                'label' => 'Acompte formation',
                'amount' => 10000,
                'description' => 'Acompte pour réserver une place en formation'
            ]
        ];

        foreach ($missingTypes as $type) {
            if (isset($defaultConfigs[$type])) {
                $config = new \App\Entity\PaymentConfiguration();
                $config->setType($type)
                    ->setLabel($defaultConfigs[$type]['label'])
                    ->setAmount($defaultConfigs[$type]['amount'])
                    ->setCurrency('XOF')
                    ->setDescription($defaultConfigs[$type]['description'])
                    ->setIsActive(true);

                $this->entityManager->persist($config);
            }
        }
        
        $this->entityManager->flush();
    }
}