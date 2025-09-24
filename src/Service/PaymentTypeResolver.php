<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Service;

use App\Entity\Payment;
use App\Entity\Rendezvous;
use App\Entity\Formation;
use App\Entity\User;
use App\Interface\PayableEntityInterface;
use App\Repository\PaymentConfigurationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PaymentTypeResolver
{
    private const ENTITY_MAPPING = [
        'rendezvous' => Rendezvous::class,
        'formation' => Formation::class,
    ];

    private const PAYMENT_TYPE_ENTITY_MAPPING = [
        'rendezvous_advance' => 'rendezvous',
        'formation_full' => 'formation',
        'formation_advance' => 'formation',
        'custom' => null, // Custom payments don't have a specific entity
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentConfigurationRepository $paymentConfigRepository
    ) {}

    public function resolveEntity(Payment $payment): ?PayableEntityInterface
    {
        if (!$payment->getEntityType() || !$payment->getEntityId()) {
            return null;
        }

        $entityClass = self::ENTITY_MAPPING[$payment->getEntityType()] ?? null;
        
        if (!$entityClass) {
            throw new BadRequestHttpException("Unknown entity type: {$payment->getEntityType()}");
        }

        $repository = $this->entityManager->getRepository($entityClass);
        $entity = $repository->find($payment->getEntityId());

        if (!$entity) {
            throw new NotFoundHttpException("Entity not found: {$payment->getEntityType()} #{$payment->getEntityId()}");
        }

        if (!$entity instanceof PayableEntityInterface) {
            throw new BadRequestHttpException("Entity does not implement PayableEntityInterface");
        }

        return $entity;
    }

    public function resolveEntityByTypeAndId(string $entityType, int $entityId): PayableEntityInterface
    {
        $entityClass = self::ENTITY_MAPPING[$entityType] ?? null;
        
        if (!$entityClass) {
            throw new BadRequestHttpException("Unknown entity type: {$entityType}");
        }

        $repository = $this->entityManager->getRepository($entityClass);
        $entity = $repository->find($entityId);

        if (!$entity) {
            throw new NotFoundHttpException("Entity not found: {$entityType} #{$entityId}");
        }

        if (!$entity instanceof PayableEntityInterface) {
            throw new BadRequestHttpException("Entity does not implement PayableEntityInterface");
        }

        return $entity;
    }

    public function validatePaymentTypeForEntity(string $paymentType, string $entityType): bool
    {
        $expectedEntityType = self::PAYMENT_TYPE_ENTITY_MAPPING[$paymentType] ?? null;
        
        if ($expectedEntityType === null) {
            // Custom payment type, can be used with any entity
            return true;
        }

        return $expectedEntityType === $entityType;
    }

    public function getPaymentAmount(string $paymentType, PayableEntityInterface $entity = null): int
    {
        // First, try to get amount from configuration
        $configuredAmount = $this->paymentConfigRepository->getAmountByType($paymentType);
        
        if ($configuredAmount > 0) {
            return (int) $configuredAmount;
        }

        // If no configuration, try to get from entity
        if ($entity) {
            return $entity->getPaymentAmount($paymentType);
        }

        // Default fallback
        return match($paymentType) {
            'rendezvous_advance' => 5000,
            'formation_full' => 0,
            'formation_advance' => 0,
            'custom' => 0,
            default => 0
        };
    }

    public function getUserForPayment(PayableEntityInterface $entity, ?User $providedUser = null): User
    {
        if ($providedUser) {
            return $providedUser;
        }

        $entityUser = $entity->getUser();
        if ($entityUser) {
            return $entityUser;
        }

        throw new BadRequestHttpException("No user associated with this payment");
    }

    public function getPaymentDescription(string $paymentType, PayableEntityInterface $entity): string
    {
        return $entity->getPaymentDescription();
    }

    public function getAvailablePaymentTypes(): array
    {
        return self::PAYMENT_TYPE_ENTITY_MAPPING;
    }

    public function getEntityTypes(): array
    {
        return array_keys(self::ENTITY_MAPPING);
    }
}