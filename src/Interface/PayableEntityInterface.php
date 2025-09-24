<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Interface;

use App\Entity\User;

interface PayableEntityInterface
{
    public function getId(): ?int;
    
    public function getUser(): ?User;
    
    public function getPaymentDescription(): string;
    
    public function getPaymentAmount(string $paymentType): int;
    
    public function onPaymentSuccess(): void;
    
    public function onPaymentFailure(): void;
    
    public function onPaymentCancellation(): void;
    
    public function getSuccessRedirectRoute(): string;
    
    public function getFailureRedirectRoute(): string;
    
    public function getEntityType(): string;
    
    public function getPaymentContext(): array;
}