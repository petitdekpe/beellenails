<?php

// src/Controller/PaymentController.php
namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Rendezvous;
use App\Service\FeexpayService;
use Symfony\Component\Mime\Email;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PaymentController extends AbstractController
{
    private $feexpayService;
    private $entityManager;

    public function __construct(FeexpayService $feexpayService, EntityManagerInterface $entityManager)
    {
        $this->feexpayService = $feexpayService;
        $this->entityManager = $entityManager;
    }

    #[Route('/rendezvous/{rendezvou}/payment', name: 'payment_form')]
    public function paymentForm(Rendezvous $rendezvou): Response
    {
        return $this->render('payment/form.html.twig', [
            'rendezvous' => $rendezvou,
        ]);
    }

    #[Route('/store-phone-number/{rendezvou}', name: 'store_phone_number')]
    public function storePhoneNumber(Request $request, SessionInterface $session, Rendezvous $rendezvou): Response
    {
        $phoneNumber = $request->get('phoneNumber');
        $session->set('phoneNumber', $phoneNumber);

        return $this->redirectToRoute('payment_init', ['rendezvou' => $rendezvou->getId()]);
    }

    #[Route('/rendezvous/{rendezvou}/payment/init', name: 'payment_init')]
    public function init(SessionInterface $session, Rendezvous $rendezvou, EntityManagerInterface $entityManager): Response
    {
        $phoneNumber = $session->get('phoneNumber');
        $amount = 5000; // Montant fixé

        if (!$phoneNumber) {
            return new Response('Phone number is not set in the session.', 400);
        }

        $provider = $this->determineProvider($phoneNumber);

        if (substr($phoneNumber, 0, 3) !== '229') {
            $phoneNumber = '229' . $phoneNumber;
        }


        if ($provider === 'mtn') {
            $response = $this->feexpayService->requestToPayMtn($phoneNumber, (float)$amount);
        } elseif ($provider === 'moov') {
            $response = $this->feexpayService->requestToPayMoov($phoneNumber, (float)$amount);
        } else {
            return new Response('Invalid phone number', 400);
        }

        // Ajout du token et sauvegarde du paiement
        //$token = $response['token']; // Supposons que vous obtenez un token dans la réponse

        $payment = new Payment();
        $payment->setDescription('Description du paiement')
        ->setAmount($amount)
            ->setCurrency('XOF')
            ->setPhoneNumber($phoneNumber)
            ->setStatus('pending')
            ->setTransactionId($response['reference'])
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTime())
            ->setReference($response['reference'])
            ->setToken($response['reference'])
            ->setMode('online')
            ->setFees(0)
            ->setCustomer($this->getUser())
            ->setRendezvous($rendezvou);

        $entityManager->persist($payment);
        $entityManager->flush();

        return $this->render('payment/notification.html.twig', [
            'payment' => $payment,
            'response' => $response,
            'reference' => $response['reference'],
        ]);
    }



    #[Route('/rendezvous/payment/callback/{reference}', name: 'payment_callback', methods: ['POST'])]
    public function callback(Request $request, string $reference, MailerInterface $mailer): Response
    {

        $statusResponse = $this->feexpayService->getTransactionStatus($reference);
        $status = $statusResponse['status'] ?? null;

        if (!$status) {
            return $this->json(['error' => 'Failed to retrieve transaction status'], 400);
        }

        $payment = $this->entityManager->getRepository(Payment::class)->findOneBy(['reference' => $reference]);
        if (!$payment) {
            return $this->json(['error' => 'Payment not found'], 400);
        }

        $rendezvous = $payment->getRendezvous();

        if ($status !== 'successful') {
            $rendezvous->setStatus('Tentative échouée');
            $payment->setStatus($status);
            $this->entityManager->flush();
            return $this->render('rendezvous/payment/error.html.twig', [
                'status' => $status,
            ]);
        }

        $payment->setStatus($status)
            ->setUpdatedAt(new \DateTime());
        $rendezvous->setStatus('Rendez-vous pris');

        $userEmail = $rendezvous->getUser()->getEmail();

        $email = (new Email())
            ->from('beellenailscare@beellenails.com')
            ->to($userEmail)
            ->subject('Informations de rendez-vous!')
            ->html($this->renderView(
                'emails/rendezvous_created.html.twig',
                ['rendezvou' => $rendezvous]
            ));
        $mailer->send($email);

        $email = (new Email())
            ->from('beellenailscare@beellenails.com')
            ->to('petitdekpe@gmail.com')
            ->subject('Nouveau Rendez-vous !')
            ->html($this->renderView(
                'emails/rendezvous_created_admin.html.twig',
                ['rendezvous' => $rendezvous]
            ));
        $mailer->send($email);



        $this->entityManager->flush();

        return $this->render('rendezvous/payment/done.html.twig', [
            'payment' => $payment,
            'status' => $status,
        ]);
    }

    private function determineProvider(string $phoneNumber): string
    {
        $prefix = substr($phoneNumber, 0, 2);
        $mtnPrefixes = ['42', '46', '50', '51', '52', '53', '54', '56', '57', '59', '61', '62', '66', '67', '69', '90', '91', '96', '97'];
        $moovPrefixes = ['55', '58', '60', '63', '64', '65', '68', '94', '95', '98', '99'];

        if (in_array($prefix, $mtnPrefixes)) {
            return 'mtn';
        } elseif (in_array($prefix, $moovPrefixes)) {
            return 'moov';
        } else {
            return 'unknown';
        }
    }
}
