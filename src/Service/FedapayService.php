<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Service;

use FedaPay\FedaPay;
use App\Entity\User;
use FedaPay\Transaction;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FedapayService
{
	protected $transaction;
	private FedaPay $fedapay;
	private Transaction $fedaTransaction;
	private string $privateKey = '';
	private string $publicKey = '';
	private string $fedapayEnv;

	public function __construct(
		string                                 $privateKey,
		string                                 $publicKey,
		string                                 $fedapayEnv,
		FedaPay                                $fedapay,
		Transaction                            $transaction,
		private readonly UrlGeneratorInterface $urlGenerator,
	)
	{
		$this->privateKey = $privateKey;
		$this->publicKey = $publicKey;
		$this->fedapayEnv = $fedapayEnv;
		$this->fedapay = $fedapay;
		$this->fedapay->setApiKey($privateKey);
		$this->fedapay->setEnvironment($fedapayEnv);
		$this->fedaTransaction = $transaction;
	}

	public function initTransaction(int $amount, string $description, User $user)
	{

		$returnUrl = $this->urlGenerator->generate('payment_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
		
		$this->transaction = $this->fedaTransaction->create([
			'description' => $description,
			'amount' => $amount,
			'currency' => ['iso' => 'XOF'],
			'callback_url' => $returnUrl,
			'customer' => $user->toArrayForPayment(),
		]);

		return $this->transaction;
	}

	public function generateToken()
	{
		return $this->transaction->generateToken();
	}

	public function getTransaction(int $transactionID)
	{
		return $this->fedaTransaction->retrieve($transactionID);
	}

}
