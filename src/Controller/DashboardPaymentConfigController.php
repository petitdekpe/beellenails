<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\PaymentConfiguration;
use App\Form\PaymentConfigurationType;
use App\Repository\PaymentConfigurationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/payment-config')]
#[IsGranted('ROLE_ADMIN')]
class DashboardPaymentConfigController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaymentConfigurationRepository $paymentConfigRepository
    ) {}

    #[Route('/', name: 'app_dashboard_payment_config_index', methods: ['GET'])]
    public function index(): Response
    {
        $configurations = $this->paymentConfigRepository->findBy([], ['type' => 'ASC']);

        return $this->render('dashboard/payment-config/index.html.twig', [
            'configurations' => $configurations,
        ]);
    }

    #[Route('/new', name: 'app_dashboard_payment_config_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $paymentConfiguration = new PaymentConfiguration();
        $form = $this->createForm(PaymentConfigurationType::class, $paymentConfiguration);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($paymentConfiguration);
            $this->entityManager->flush();

            $this->addFlash('success', 'Configuration de paiement créée avec succès.');

            return $this->redirectToRoute('app_dashboard_payment_config_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/payment-config/new.html.twig', [
            'payment_configuration' => $paymentConfiguration,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_dashboard_payment_config_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(?PaymentConfiguration $paymentConfiguration): Response
    {
        if (!$paymentConfiguration) {
            $this->addFlash('error', 'Configuration introuvable.');
            return $this->redirectToRoute('app_dashboard_payment_config_index');
        }

        return $this->render('dashboard/payment-config/show.html.twig', [
            'payment_configuration' => $paymentConfiguration,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_dashboard_payment_config_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, ?PaymentConfiguration $paymentConfiguration): Response
    {
        if (!$paymentConfiguration) {
            $this->addFlash('error', 'Configuration introuvable.');
            return $this->redirectToRoute('app_dashboard_payment_config_index');
        }

        $form = $this->createForm(PaymentConfigurationType::class, $paymentConfiguration, [
            'edit_mode' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $paymentConfiguration->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->addFlash('success', 'Configuration de paiement mise à jour avec succès.');

            return $this->redirectToRoute('app_dashboard_payment_config_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/payment-config/edit.html.twig', [
            'payment_configuration' => $paymentConfiguration,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_dashboard_payment_config_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(?PaymentConfiguration $paymentConfiguration): Response
    {
        if (!$paymentConfiguration) {
            $this->addFlash('error', 'Configuration introuvable.');
            return $this->redirectToRoute('app_dashboard_payment_config_index');
        }

        $paymentConfiguration->setIsActive(!$paymentConfiguration->isActive());
        $paymentConfiguration->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $status = $paymentConfiguration->isActive() ? 'activée' : 'désactivée';
        $this->addFlash('success', 'Configuration ' . $status . ' avec succès.');

        return $this->redirectToRoute('app_dashboard_payment_config_index');
    }

    #[Route('/{id}', name: 'app_dashboard_payment_config_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, ?PaymentConfiguration $paymentConfiguration): Response
    {
        if (!$paymentConfiguration) {
            $this->addFlash('error', 'Configuration introuvable.');
            return $this->redirectToRoute('app_dashboard_payment_config_index');
        }

        if ($this->isCsrfTokenValid('delete'.$paymentConfiguration->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($paymentConfiguration);
            $this->entityManager->flush();

            $this->addFlash('success', 'Configuration de paiement supprimée avec succès.');
        }

        return $this->redirectToRoute('app_dashboard_payment_config_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/init-defaults', name: 'app_dashboard_payment_config_init_defaults', methods: ['POST'])]
    public function initDefaults(): Response
    {
        $defaultConfigs = [
            [
                'type' => 'rendezvous_advance',
                'label' => 'Acompte pour rendez-vous',
                'amount' => 5000,
                'description' => 'Montant de l\'acompte demandé lors de la réservation d\'un rendez-vous'
            ],
            [
                'type' => 'formation_full',
                'label' => 'Formation complète',
                'amount' => 25000,
                'description' => 'Prix complet pour l\'accès à une formation'
            ],
            [
                'type' => 'formation_advance',
                'label' => 'Acompte formation',
                'amount' => 10000,
                'description' => 'Acompte pour réserver une place en formation'
            ],
            [
                'type' => 'custom',
                'label' => 'Paiement personnalisé',
                'amount' => 0,
                'description' => 'Montant configurable pour les paiements personnalisés'
            ]
        ];

        $createdCount = 0;
        foreach ($defaultConfigs as $configData) {
            $existing = $this->paymentConfigRepository->findOneBy(['type' => $configData['type']]);
            
            if (!$existing) {
                $config = new PaymentConfiguration();
                $config->setType($configData['type'])
                    ->setLabel($configData['label'])
                    ->setAmount($configData['amount'])
                    ->setCurrency('XOF')
                    ->setDescription($configData['description'])
                    ->setIsActive(true);

                $this->entityManager->persist($config);
                $createdCount++;
            }
        }

        if ($createdCount > 0) {
            $this->entityManager->flush();
            $this->addFlash('success', "{$createdCount} configuration(s) par défaut créée(s) avec succès.");
        } else {
            $this->addFlash('info', 'Toutes les configurations par défaut existent déjà.');
        }

        return $this->redirectToRoute('app_dashboard_payment_config_index');
    }
}