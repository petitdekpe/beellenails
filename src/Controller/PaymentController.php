<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>


namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Rendezvous;
use App\Service\FedapayService;
use Symfony\Component\Mime\Email;
use App\Repository\UserRepository;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PaymentController extends AbstractController
{
    public function __construct(
        private readonly FedapayService $fedapayService,
        private readonly EntityManagerInterface $entityManager
    ) {}

    /**
     * @throws \Exception
     */
    #[Route('/rendezvous/{rendezvous}/payment/init', name: 'payment_init')]
    public function init(Rendezvous $rendezvous, UserRepository $userRepository): Response
    {

        $user = $rendezvous->getUser();

        /** @var \FedaPay\Transaction $transaction */
        $transaction = $this->fedapayService->initTransaction(
            //montant
            5000,
            //description
            'Acompte sur Prestation',
            //utilisateur
            $user
        );

        $token = $this->fedapayService->generateToken();

        $payment = new Payment();
        $payment->parseTransaction($transaction)
            ->setRendezvous($rendezvous)
            ->setCustomer($user)
            ->setPhoneNumber($user->getPhone())
            ->setToken($token->token)
            ->setProvider('fedapay');
        $this->entityManager->persist($user);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $this->render('redirect.html.twig', [
            'redirect_url' => $token->url
        ]);
    }

    #[Route('/rendezvous/payment/callback', name: 'payment_callback')]
    public function callback(Request $request, PaymentRepository $repository, UserInterface $user, MailerInterface $mailer): Response
    {
        $transactionID = $request->get('id');
        $status = $request->get('status');

        $this->entityManager->beginTransaction();
        try {
            $payment = $repository->findOneBy(['transactionID' => $transactionID]);
            $this->entityManager->lock($payment, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);

            $rendezvous = $payment->getRendezvous();

            if ($payment->getStatus() === 'approved' || $rendezvous->getStatus() === 'Rendez-vous pris') {
                $this->entityManager->commit();
                $this->addFlash('error', 'Rendez-vous déjà confirmé !');
                return $this->render('rendezvous/payment/done.html.twig', [
                    'payment' => $payment,
                    'status'  => $status,
                ]);
            }

            if ($status !== 'approved') {
                $rendezvous->setStatus('Tentative échouée');
                $this->entityManager->flush();
                $this->entityManager->commit();
                return $this->render('rendezvous/payment/error.html.twig', [
                    'status' => $status,
                ]);
            }

            $payment->setUpdatedAt(new \DateTime('now'))->setStatus($status);
            $rendezvous->setStatus('Rendez-vous pris');

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $this->render('rendezvous/payment/done.html.twig', [
                'payment' => $payment,
                'status'  => $status,
            ]);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    #[Route('/payment/callback', name: 'feda_callback', methods: ['POST'], format: 'json')]
    public function fedaCallback(Request $request, PaymentRepository $repository, MailerInterface $mailer): Response
    {
        $data = json_decode($request->getContent(), true)['entity'];
        $transactionID = $data['id'];
        $status = $data['status'];

        $this->entityManager->beginTransaction();
        try {
            $payment = $repository->findOneBy(['transactionID' => $transactionID]);

            if ($payment === null) {
                return $this->json(['status' => $status], 400);
            }

            $this->entityManager->lock($payment, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            $rendezvous = $payment->getRendezvous();

            if ($rendezvous->getStatus() === 'Rendez-vous pris') {
                $this->entityManager->commit();
                return $this->json(['status' => 'already_taken'], 200);
            }

            if ($status === 'canceled' || $status === 'declined') {
                $rendezvous->setStatus('Échec du paiement');
                $payment->setStatus($status);
            } elseif ($status === 'approved') {
                $transaction = $this->fedapayService->getTransaction($transactionID);

                $rendezvous->setPaid(true);
                $rendezvous->setStatus('Rendez-vous pris');

                $userEmail = $rendezvous->getUser()->getEmail();
                $email = (new Email())
                    ->from('beellenailscare@beellenails.com')
                    ->to($userEmail)
                    ->subject('Informations de rendez-vous!')
                    ->html($this->renderView('emails/rendezvous_created.html.twig', [
                        'rendezvous' => $rendezvous
                    ]));
                $mailer->send($email);

                $adminEmail = (new Email())
                    ->from('beellenailscare@beellenails.com')
                    ->to('jy.ahouanvoedo@gmail.com')
                    ->subject('Nouveau Rendez-vous !')
                    ->html($this->renderView('emails/rendezvous_created_admin.html.twig', [
                        'rendezvous' => $rendezvous
                    ]));
                $mailer->send($adminEmail);

                $payment->setUpdatedAt(new \DateTime('now'))->setStatus($status);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
            return $this->json(['status' => $status]);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}
