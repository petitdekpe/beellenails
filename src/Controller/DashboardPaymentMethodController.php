<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\PaymentMethod;
use App\Form\PaymentMethodType;
use App\Repository\PaymentMethodRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/payment-methods')]
#[IsGranted("ROLE_ADMIN")]
class DashboardPaymentMethodController extends AbstractController
{
    #[Route('/', name: 'app_dashboard_payment_method_index', methods: ['GET'])]
    public function index(PaymentMethodRepository $paymentMethodRepository): Response
    {
        return $this->render('dashboard/payment_method/index.html.twig', [
            'payment_methods' => $paymentMethodRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_dashboard_payment_method_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $paymentMethod = new PaymentMethod();
        $form = $this->createForm(PaymentMethodType::class, $paymentMethod);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($paymentMethod);
            $entityManager->flush();

            $this->addFlash('success', 'Méthode de paiement ajoutée avec succès.');
            return $this->redirectToRoute('app_dashboard_payment_method_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/payment_method/new.html.twig', [
            'payment_method' => $paymentMethod,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_dashboard_payment_method_show', methods: ['GET'])]
    public function show(PaymentMethod $paymentMethod): Response
    {
        return $this->render('dashboard/payment_method/show.html.twig', [
            'payment_method' => $paymentMethod,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_dashboard_payment_method_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PaymentMethod $paymentMethod, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PaymentMethodType::class, $paymentMethod);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Méthode de paiement modifiée avec succès.');
            return $this->redirectToRoute('app_dashboard_payment_method_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/payment_method/edit.html.twig', [
            'payment_method' => $paymentMethod,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_dashboard_payment_method_toggle', methods: ['POST'])]
    public function toggle(PaymentMethod $paymentMethod, EntityManagerInterface $entityManager): Response
    {
        $paymentMethod->setIsActive(!$paymentMethod->isActive());
        $entityManager->flush();

        $status = $paymentMethod->isActive() ? 'activée' : 'désactivée';
        $this->addFlash('success', sprintf('Méthode de paiement "%s" %s avec succès.', $paymentMethod->getName(), $status));

        return $this->redirectToRoute('app_dashboard_payment_method_index');
    }

    #[Route('/{id}', name: 'app_dashboard_payment_method_delete', methods: ['POST'])]
    public function delete(Request $request, PaymentMethod $paymentMethod, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$paymentMethod->getId(), $request->request->get('_token'))) {
            $entityManager->remove($paymentMethod);
            $entityManager->flush();
            
            $this->addFlash('success', 'Méthode de paiement supprimée avec succès.');
        }

        return $this->redirectToRoute('app_dashboard_payment_method_index', [], Response::HTTP_SEE_OTHER);
    }
}