<?php

namespace App\Controller;

use App\Entity\User;
use DateTimeImmutable;
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
	public function __construct(private readonly FedapayService $fedapayService, private readonly EntityManagerInterface $entityManager) { }

	/**
	 * @throws \Exception
	 */
	#[Route('/rendezvous/{rendezvou}/payment/init', name: 'payment_init')]
	public function init(Rendezvous $rendezvou, UserRepository $userRepository): Response
	{

		$user=$rendezvou->getUser();

		/** @var \FedaPay\Transaction $transaction */
		$transaction = $this->fedapayService->initTransaction(
			//montant
			100,
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
		        ->setToken($token->token)
		;
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

		if ($status !== 'approved') {
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
			if ($payment->getStatus() !== 'pending') {
				$this->addFlash('error', 'Rendez-vous invalide !');
				return $this->render('rendezvous/payment/error.html.twig', [
					'status' => "invalid",
				]);
			}
		}

	
		$transaction = $this->fedapayService->getTransaction($transactionID);
		$rendezvou = $payment->getRendezvous();
		$rendezvou->setPaid(true);

		$userEmail = $rendezvou->getUser()->getEmail();

		// Envoyer l'e-mail après la création du rendez-vous
		$email = (new Email())
		->from('noreply@beellenails.com')
		->to($userEmail)
		->subject('Votre Rendez-vous !')
		->html($this->renderView(
			'emails/rendezvous_created.html.twig',
			['rendezvous' => $rendezvou]
		));

		$mailer->send($email);

        // TODO : consulter la variable $transaction pour les infos de FEDAPAY

		$status = $transaction->status;

		$payment
			->setUpdatedAt(new \DateTime('now'))
			->setStatus($status)
			->setFees($transaction->fees ?? 0)
			->setMode($transaction->mode ?? '')
		;
		
		$this->entityManager->flush();

		return $this->render('rendezvous/payment/done.html.twig', [
			'payment' => $payment,
			'status' => $status,
		]);
	}

}
