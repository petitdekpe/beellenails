<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


// src/Controller/PaymentChoiceController.php
namespace App\Controller;

use App\Entity\Rendezvous;
use App\Repository\PaymentMethodRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PaymentChoiceController extends AbstractController
{
    #[Route('/payment-choice/{rendezvous}', name: 'payment_choice')]
    public function choosePayment(Rendezvous $rendezvous, PaymentMethodRepository $paymentMethodRepository): Response
        {
            $activePaymentMethods = $paymentMethodRepository->findActivePaymentMethods();
            
            return $this->render('payment_choice/index.html.twig', [
                'rendezvous' => $rendezvous,
                'payment_methods' => $activePaymentMethods,
            ]);
        }
}