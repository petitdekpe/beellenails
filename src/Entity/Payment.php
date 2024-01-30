<?php

namespace App\Entity;

use FedaPay\Transaction;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\PaymentRepository;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
	public const STATUS = [
		'pending' => 'En attente',
		'approved' => 'Approuvée',
		'declined' => 'Déclinée',
		'canceled' => 'Annulée',
		'refunded' => 'Remboursée',
		'transferred' => 'Transférée',
		'invalid' => "Invalide",
	];

	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $description = null;

	#[ORM\Column]
	private ?int $amount = null;

	#[ORM\Column(length: 5)]
	private ?string $currency = null;

	#[ORM\Column(length: 255)]
	private ?string $phoneNumber = null;

	#[ORM\Column(length: 255)]
	private ?string $status = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $transactionID = null;

	#[ORM\ManyToOne(inversedBy: 'payments')]
	#[ORM\JoinColumn(nullable: false)]
	private ?User $customer = null;

	#[ORM\OneToOne(inversedBy: 'payment', cascade: ['persist', 'remove'])]
	private ?Rendezvous $rendezvou = null;

	#[ORM\Column]
	private ?\DateTimeImmutable $createdAt = null;

	#[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
	private ?\DateTimeInterface $updatedAt = null;

	#[ORM\Column(length: 255)]
	private ?string $reference = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $token = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $mode = null;

	#[ORM\Column(nullable: true)]
	private ?int $fees = null;

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getDescription(): ?string
	{
		return $this->description;
	}

	public function setDescription(?string $description): self
	{
		$this->description = $description;

		return $this;
	}

	public function getAmount(): ?int
	{
		return $this->amount;
	}

	public function setAmount(int $amount): self
	{
		$this->amount = $amount;

		return $this;
	}

	public function getCurrency(): ?string
	{
		return $this->currency;
	}

	public function setCurrency(string $currency): self
	{
		$this->currency = $currency;

		return $this;
	}

	public function getPhoneNumber(): ?string
	{
		return $this->phoneNumber;
	}

	public function setPhoneNumber(string $phoneNumber): self
	{
		$this->phoneNumber = $phoneNumber;

		return $this;
	}

	public function getStatus(): ?string
	{
		return $this->status;
	}

	public function setStatus(string $status): self
	{
		if (!array_key_exists($status, self::STATUS)) {
			throw new \InvalidArgumentException('Invalid status');
		}
		$this->status = $status;

		return $this;
	}

	public function getTransactionID(): ?string
	{
		return $this->transactionID;
	}

	public function setTransactionID(?string $transactionID): self
	{
		$this->transactionID = $transactionID;

		return $this;
	}

	public function getCustomer(): ?User
	{
		return $this->customer;
	}

	public function setCustomer(?User $customer): self
	{
		$this->customer = $customer;

		return $this;
	}

	public function getRendezvous(): ?Rendezvous
	{
		return $this->rendezvou;
	}

	public function setRendezvous(?Rendezvous $rendezvou): self
	{
		$this->rendezvou = $rendezvou;

		return $this;
	}

	public function getCreatedAt(): ?\DateTimeImmutable
	{
		return $this->createdAt;
	}

	public function setCreatedAt(\DateTimeImmutable $createdAt): self
	{
		$this->createdAt = $createdAt;

		return $this;
	}

	public function getUpdatedAt(): ?\DateTimeInterface
	{
		return $this->updatedAt;
	}

	public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
	{
		$this->updatedAt = $updatedAt;

		return $this;
	}

	public function getReference(): ?string
	{
		return $this->reference;
	}

	public function setReference(string $reference): self
	{
		$this->reference = $reference;

		return $this;
	}

	/**
	 * @throws \Exception
	 */
	public function parseTransaction(Transaction $transaction): self
	{
		$this
			->setTransactionID($transaction->id)
			->setAmount($transaction->amount)
			->setCurrency('XOF')
			->setCreatedAt(new \DateTimeImmutable($transaction->created_at))
			->setUpdatedAt(new \DateTime($transaction->updated_at))
			->setReference($transaction->reference)
			->setDescription($transaction->description)
			->setStatus($transaction->status)
		;

		return $this;
	}

	public function getToken(): ?string
	{
		return $this->token;
	}

	public function setToken(?string $token): self
	{
		$this->token = $token;

		return $this;
	}

	public function getMode(): ?string
	{
		return $this->mode;
	}

	public function setMode(?string $mode): self
	{
		$this->mode = $mode;

		return $this;
	}

	public function getFees(): ?int
	{
		return $this->fees;
	}

	public function setFees(?int $fees): self
	{
		$this->fees = $fees;

		return $this;
	}
}
