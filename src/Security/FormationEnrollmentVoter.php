<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Security;

use App\Entity\FormationEnrollment;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class FormationEnrollmentVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT])
            && $subject instanceof FormationEnrollment;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // L'utilisateur doit être connecté
        if (!$user instanceof User) {
            return false;
        }

        /** @var FormationEnrollment $enrollment */
        $enrollment = $subject;

        return match($attribute) {
            self::VIEW => $this->canView($enrollment, $user),
            self::EDIT => $this->canEdit($enrollment, $user),
            default => false,
        };
    }

    private function canView(FormationEnrollment $enrollment, User $user): bool
    {
        // Un utilisateur peut voir sa propre inscription
        if ($enrollment->getUser() === $user) {
            return true;
        }

        // Les administrateurs peuvent voir toutes les inscriptions
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        return false;
    }

    private function canEdit(FormationEnrollment $enrollment, User $user): bool
    {
        // Un utilisateur peut modifier sa propre inscription (pour le progrès par exemple)
        if ($enrollment->getUser() === $user) {
            return true;
        }

        // Les administrateurs peuvent modifier toutes les inscriptions
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        return false;
    }
}