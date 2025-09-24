<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Twig;

use App\Entity\User;
use App\Repository\FormationEnrollmentRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FormationExtension extends AbstractExtension
{
    public function __construct(
        private FormationEnrollmentRepository $enrollmentRepository
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_user_active_enrollments', [$this, 'getUserActiveEnrollments']),
        ];
    }

    public function getUserActiveEnrollments(?User $user): array
    {
        if (!$user) {
            return [];
        }

        return $this->enrollmentRepository->findBy([
            'user' => $user,
            'status' => 'active'
        ], ['lastAccessedAt' => 'DESC']);
    }
}