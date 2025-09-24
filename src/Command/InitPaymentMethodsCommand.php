<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Command;

use App\Entity\PaymentMethod;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-payment-methods',
    description: 'Initialize default payment methods'
)]
class InitPaymentMethodsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $paymentMethods = [
            [
                'name' => 'Fedapay',
                'identifier' => 'fedapay',
                'logoPath' => '../assets/feda-logo.svg',
                'description' => 'Paiement sécurisé par carte bancaire ou mobile money',
                'isActive' => true
            ],
            [
                'name' => 'Feexpay',
                'identifier' => 'feexpay',
                'logoPath' => '../assets/feexpay-logo.png',
                'description' => 'Solution de paiement mobile simple et rapide',
                'isActive' => true
            ]
        ];

        $created = 0;

        foreach ($paymentMethods as $methodData) {
            $existing = $this->entityManager->getRepository(PaymentMethod::class)
                ->findOneBy(['identifier' => $methodData['identifier']]);

            if (!$existing) {
                $method = new PaymentMethod();
                $method->setName($methodData['name'])
                    ->setIdentifier($methodData['identifier'])
                    ->setLogoPath($methodData['logoPath'])
                    ->setDescription($methodData['description'])
                    ->setIsActive($methodData['isActive']);

                $this->entityManager->persist($method);
                $created++;

                $io->success(sprintf('Méthode de paiement "%s" créée.', $methodData['name']));
            } else {
                $io->note(sprintf('Méthode de paiement "%s" existe déjà.', $methodData['name']));
            }
        }

        if ($created > 0) {
            $this->entityManager->flush();
            $io->success(sprintf('%d méthode(s) de paiement créée(s) avec succès.', $created));
        } else {
            $io->info('Toutes les méthodes de paiement existent déjà.');
        }

        return Command::SUCCESS;
    }
}