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


	public function generateToken()
	{
		return $this->transaction->generateToken();
	}

	public function getTransaction(int $transactionID)
	{
		return $this->fedaTransaction->retrieve($transactionID);
	}

	public function createGenericTransaction(
		int $amount,
		string $description,
		User $user,
		string $customReference = null
	) {
		$returnUrl = $this->urlGenerator->generate('generic_payment_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
		
		$transactionData = [
			'description' => $description,
			'amount' => $amount,
			'currency' => ['iso' => 'XOF'],
			'callback_url' => $returnUrl,
			'customer' => $user->toArrayForPayment(),
		];

		if ($customReference) {
			$transactionData['reference'] = $customReference;
		}

		$this->transaction = $this->fedaTransaction->create($transactionData);
		return $this->transaction;
	}

	public function getTransactionAmount($transaction): int
	{
		return $transaction->amount ?? 0;
	}

	public function getTransactionStatus($transaction): string
	{
		return $transaction->status ?? 'unknown';
	}

	public function getTransactionReference($transaction): ?string
	{
		return $transaction->reference ?? null;
	}

	public function isTransactionSuccessful($transaction): bool
	{
		return in_array($transaction->status ?? '', ['approved', 'successful']);
	}

}
