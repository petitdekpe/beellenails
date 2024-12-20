<?php

// src/Controller/PaymentChoiceController.php
namespace App\Controller;

use App\Entity\Rendezvous;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PaymentChoiceController extends AbstractController
{
    #[Route('/payment-choice/{rendezvou}', name: 'payment_choice')]
    public function choosePayment(Rendezvous $rendezvou): Response
        {
            return $this->render('payment_choice/index.html.twig', [
                'rendezvous' => $rendezvou,
            ]);
        }
}