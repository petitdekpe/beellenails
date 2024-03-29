<?php

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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PaymentController extends AbstractController
{
    public function __construct(private readonly FedapayService $fedapayService, private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @throws \Exception
     */
    #[Route('/rendezvous/{rendezvou}/payment/init', name: 'payment_init')]
    public function init(Rendezvous $rendezvou, UserRepository $userRepository): Response
    {

        $user = $rendezvou->getUser();

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
            ->setRendezvous($rendezvou)
            ->setCustomer($user)
            ->setPhoneNumber($user->getPhone())
            ->setToken($token->token);
        $this->entityManager->persist($user);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $this->redirect($token->url);
    }

    #[Route('/rendezvous/payment/callback', name: 'payment_callback')]
    public function callback(Request $request, PaymentRepository $repository, UserInterface $user, MailerInterface $mailer): Response
    {
        $transactionID = $request->get('id');
        $status = $request->get('status');
        $payment = $repository->findOneBy(['transactionID' => $transactionID]);
        $rendezvou = $payment->getRendezvous();
        if ($status !== 'approved') {
            $rendezvou->setStatus('Tentative échoué');
            $this->entityManager->flush();
            return $this->render('rendezvous/payment/error.html.twig', [
                'status' => $status,
            ]);
        }

        $payment = $repository->findOneBy(['transactionID' => $transactionID]);

        if ($payment === null) {
            $this->addFlash('error', 'Rendez-vous inconnue !. Veuillez réessayer.');
            return $this->render('rendezvous/payment/error.html.twig', [
                'status' => "invalid",
            ]);
        } else {
            if ($payment->getStatus() == 'approved') {
                $this->addFlash('error', 'Rendez-vous invalide !');
                return $this->render('rendezvous/payment/done.html.twig', [
                    'payment' => $payment,
                    'status'  => $status,
                ]);
            }
            if ($payment->getStatus() !== 'pending') {
                $this->addFlash('error', 'Rendez-vous invalide !');
                return $this->render('rendezvous/payment/error.html.twig', [
                    'status' => "invalid",
                ]);
            }
        }
        $payment
            ->setUpdatedAt(new \DateTime('now'))
            ->setFees($transaction->fees ?? 0)
            ->setMode($transaction->mode ?? '');

        $this->entityManager->flush();

        return $this->render('rendezvous/payment/done.html.twig', [
            'payment' => $payment,
            'status'  => $status,
        ]);
    }

    #[Route('/payment/callback', name: 'feda_callback', methods: ['POST'], format: 'json')]
    public function fedaCallback(Request $request, PaymentRepository $repository, MailerInterface $mailer): ?Response
    {
        $data = json_decode($request->getContent(), true)['entity'];
        $transactionID = $data['id'];
        $status = $data['status'];
        $payment = $repository->findOneBy(['transactionID' => $transactionID]);
        if ($payment === null) {
            return $this->json(['status' => 'error'], 400);
        }
        $rendezvou = $payment->getRendezvous();

        if ($status == 'canceled' || $status == 'declined') {
            $rendezvou->setStatus('Échec du paiement');
            $payment->setStatus($status);
            $this->entityManager->flush();
        } else if ($status == 'approved') {
            $transaction = $this->fedapayService->getTransaction($transactionID);
            if ($rendezvou->getStatus() === 'Rendez-vous pris' || $rendezvou->isPaid()) {
                return $this->json(['status' => 'done']);
            }
            $rendezvou->setPaid(true);
            // Mettre à jour la variable 'status' dans rdv en fonction du statut du paiement
            //if ($status === 'approved') {
            //	$rendezvou->setStatus('Rendez-vous pris');
            //} else {
            //	$rendezvou->setStatus('Echec du paiement');
            //}

            $rendezvou->setStatus('Rendez-vous pris');

            $userEmail = $rendezvou->getUser()->getEmail();

            $email = (new Email())
                ->from('beellenailscare@beellenails.com')
                ->to($userEmail)
                ->subject('Informations de rendez-vous!')
                ->html($this->renderView(
                    'emails/rendezvous_created.html.twig',
                    ['rendezvou' => $rendezvou]
                ));
            $mailer->send($email);

            $email = (new Email())
                ->from('beellenailscare@beellenails.com')
                ->to('murielahodode@gmail.com')
                ->subject('Nouveau Rendez-vous !')
                ->html($this->renderView(
                    'emails/rendezvous_created_admin.html.twig',
                    ['rendezvous' => $rendezvou]
                ));
            $mailer->send($email);

            $status = $transaction->status;

            $payment
                ->setUpdatedAt(new \DateTime('now'))
                ->setStatus($status)
                ->setFees($transaction->fees ?? 0)
                ->setMode($transaction->mode ?? '');

            $this->entityManager->flush();
        }

        return $this->json(['status' => $status], 400);
    }

}
