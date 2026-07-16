<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\Payment;
use App\Repository\PaymentRepository;
use App\Service\PaymentTypeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/dashboard/payment-conflicts')]
class DashboardPaymentConflictController extends AbstractController
{
    #[Route('', name: 'app_dashboard_payment_conflicts_index', methods: ['GET'])]
    public function index(PaymentRepository $paymentRepository, PaymentTypeResolver $paymentTypeResolver): Response
    {
        $conflicts = $paymentRepository->findConflicts();
        $resolved = $paymentRepository->findResolvedConflicts();

        $conflictRows = array_map(fn (Payment $payment) => [
            'payment' => $payment,
            'entity' => $this->safeResolveEntity($paymentTypeResolver, $payment),
        ], $conflicts);

        $resolvedRows = array_map(fn (Payment $payment) => [
            'payment' => $payment,
            'entity' => $this->safeResolveEntity($paymentTypeResolver, $payment),
        ], $resolved);

        return $this->render('dashboard/payment-conflicts/index.html.twig', [
            'conflictRows' => $conflictRows,
            'resolvedRows' => $resolvedRows,
        ]);
    }

    #[Route('/{id}/resolve', name: 'app_dashboard_payment_conflicts_resolve', methods: ['POST'])]
    public function resolve(Request $request, Payment $payment, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('payment_conflict_resolve_' . $payment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_dashboard_payment_conflicts_index');
        }

        if ($payment->getStatus() !== 'conflict') {
            $this->addFlash('error', 'Ce paiement n\'est pas (ou plus) en conflit.');
            return $this->redirectToRoute('app_dashboard_payment_conflicts_index');
        }

        $payment->setStatus('refunded');
        $payment->setUpdatedAt(new \DateTime());
        $em->flush();

        $this->addFlash('success', 'Remboursement marqué comme effectué pour la référence ' . $payment->getReference() . '.');

        return $this->redirectToRoute('app_dashboard_payment_conflicts_index');
    }

    private function safeResolveEntity(PaymentTypeResolver $paymentTypeResolver, Payment $payment): mixed
    {
        try {
            return $paymentTypeResolver->resolveEntity($payment);
        } catch (\Exception $e) {
            return null;
        }
    }
}
