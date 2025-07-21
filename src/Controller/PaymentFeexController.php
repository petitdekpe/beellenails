<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Rendezvous;
use App\Form\FeexPayFormType;
use App\Service\FeexpayService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class PaymentFeexController extends AbstractController
{
    #[Route('/payment/feexpay/form/{rendezvou}', name: 'feexpay_form')]
    public function form(Request $request, Rendezvous $rendezvou): Response
    {
        $form = $this->createForm(FeexPayFormType::class);

        return $this->render('feexpay/form.html.twig', [
            'form' => $form->createView(),
            'rendezvous' => $rendezvou,
        ]);
    }

    #[Route('/payment/feexpay/init/{rendezvou}', name: 'feexpay_payment_init', methods: ['POST'])]
    public function init(
        Request $request,
        Rendezvous $rendezvou,
        FeexpayService $feexpayService,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(FeexPayFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('feexpay/form.html.twig', [
                'form' => $form->createView(),
                'rendezvous' => $rendezvou,
                'error' => 'Formulaire invalide',
            ]);
        }

        $data = $form->getData();
        $user = $rendezvou->getUser();

        $response = $feexpayService->paiementLocal(
            5000,
            $data['phone'],
            $data['operator'],
            $user->__toString(),
            $user->getEmail(),
            'rendezvous_' . $rendezvou->getId()
        );

        if (!isset($response['reference'])) {
            return $this->render('feexpay/form.html.twig', [
                'form' => $form->createView(),
                'rendezvous' => $rendezvou,
                'error' => $response['message'] ?? 'Erreur FeexPay',
                'debug' => $response
            ]);
        }

        $payment = new Payment();
        $payment->initializeForFeexPay(
            transactionID: $response['reference'],
            reference: $response['reference'],
            phoneNumber: $data['phone'],
            customer: $user,
            rendezvous: $rendezvou,
            mode: $feexpayService->getMode(),
            provider: 'feexpay'
        );

        $em->persist($payment);
        $em->flush();

        return $this->render('feexpay/success.html.twig', [
            'reference' => $response['reference'],
            'response' => $response,
        ]);
    }

    #[Route('/payment/feexpay/status/{reference}', name: 'feexpay_payment_status', methods: ['GET'])]
    public function status(
        string $reference,
        FeexpayService $feexpayService,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        Request $request,
        MailerInterface $mailer
    ): Response {
        $payment = $em->getRepository(Payment::class)->findOneBy(['reference' => $reference]);

        if (!$payment) {
            throw $this->createNotFoundException('Paiement introuvable');
        }

        $attempt = $request->query->getInt('attempt', 0);
        $result = $feexpayService->getPaiementStatus($reference);

        if (isset($result['status'])) {
            $feexStatus = $result['status'];
            $internalStatus = Payment::convertFeexStatus($feexStatus);

            $payment->setStatus($internalStatus)->setUpdatedAt(new \DateTime());
            $rendezvous = $payment->getRendezvous();

            if ($internalStatus === 'successful') {
                $rendezvous->setPaid(true)->setStatus('Rendez-vous pris');

                // Envoi d'email au client
                $userEmail = $rendezvous->getUser()->getEmail();
                $email = (new Email())
                    ->from('beellenailscare@beellenails.com')
                    ->to($userEmail)
                    ->subject('Informations de rendez-vous!')
                    ->html($this->renderView('emails/rendezvous_created.html.twig', [
                        'rendezvous' => $rendezvous
                    ]));
                $mailer->send($email);

                // Envoi d'email à l'admin
                $adminEmail = (new Email())
                    ->from('beellenailscare@beellenails.com')
                    ->to('murielahodode@gmail.com')
                    ->subject('Nouveau Rendez-vous !')
                    ->html($this->renderView('emails/rendezvous_created_admin.html.twig', [
                        'rendezvous' => $rendezvous
                    ]));
                $mailer->send($adminEmail);
            } elseif ($internalStatus === 'failed') {
                $rendezvous->setStatus('Échec du paiement');
            } elseif ($internalStatus === 'canceled') {
                $rendezvous->setStatus('Paiement annulé');
            } elseif ($internalStatus === 'pending') {
                $rendezvous->setStatus('Paiement en attente');
            }

            $em->flush();

            $logger->info(sprintf(
                '[FeexPay][Tentative #%d] Réf: %s - Statut brut: %s (interne: %s)',
                $attempt,
                $reference,
                $feexStatus,
                $internalStatus
            ));
        }

        return $this->json([
            'reference' => $reference,
            'status' => $payment->getStatus(),
            'feex_status' => $result['status'] ?? 'UNKNOWN',
        ]);
    }

    #[Route('/rendezvous/payment/done/{reference}', name: 'rendezvous_payment_done')]
    public function paymentDone(string $reference, EntityManagerInterface $em): Response
    {
        $payment = $em->getRepository(Payment::class)->findOneBy(['reference' => $reference]);

        if (!$payment) {
            throw $this->createNotFoundException('Paiement introuvable');
        }

        return $this->render('rendezvous/payment/done.html.twig', [
            'payment' => $payment,
        ]);
    }

    #[Route('/rendezvous/payment/error/{reference}', name: 'rendezvous_payment_error')]
    public function paymentError(string $reference, EntityManagerInterface $em): Response
    {
        $payment = $em->getRepository(Payment::class)->findOneBy(['reference' => $reference]);

        if (!$payment) {
            throw $this->createNotFoundException('Paiement introuvable');
        }

        return $this->render('rendezvous/payment/error.html.twig', [
            'payment' => $payment,
        ]);
    }
}
