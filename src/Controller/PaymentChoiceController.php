<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>


// src/Controller/PaymentChoiceController.php
namespace App\Controller;

use App\Entity\Rendezvous;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PaymentChoiceController extends AbstractController
{
    #[Route('/payment-choice/{rendezvous}', name: 'payment_choice')]
    public function choosePayment(Rendezvous $rendezvous): Response
        {
            return $this->render('payment_choice/index.html.twig', [
                'rendezvous' => $rendezvous,
            ]);
        }
}