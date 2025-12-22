<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\EventListener;

use App\Entity\FormationModule;
use App\Entity\ModuleProgress;
use App\Repository\FormationEnrollmentRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist, priority: 500)]
#[AsDoctrineListener(event: Events::preUpdate, priority: 500)]
class FormationModuleListener
{
    public function __construct(
        private FormationEnrollmentRepository $enrollmentRepository,
        private EntityManagerInterface $entityManager
    ) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        // Vérifier si c'est un FormationModule
        if (!$entity instanceof FormationModule) {
            return;
        }

        // Si le module est actif, créer les ModuleProgress pour tous les utilisateurs inscrits
        if ($entity->isActive()) {
            $this->createModuleProgressForEnrolledUsers($entity);
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        // Vérifier si c'est un FormationModule
        if (!$entity instanceof FormationModule) {
            return;
        }

        // Vérifier si le module vient d'être activé
        if ($args->hasChangedField('active') && $entity->isActive()) {
            $this->createModuleProgressForEnrolledUsers($entity);
        }
    }

    private function createModuleProgressForEnrolledUsers(FormationModule $module): void
    {
        $formation = $module->getFormation();

        // Récupérer toutes les inscriptions actives pour cette formation
        $enrollments = $this->enrollmentRepository->findBy([
            'formation' => $formation,
            'status' => 'active'
        ]);

        foreach ($enrollments as $enrollment) {
            // Vérifier si un ModuleProgress existe déjà
            $existingProgress = $this->entityManager->getRepository(ModuleProgress::class)
                ->findOneBy([
                    'enrollment' => $enrollment,
                    'module' => $module
                ]);

            // Si pas de progress, on le crée
            if (!$existingProgress) {
                $moduleProgress = new ModuleProgress();
                $moduleProgress->setEnrollment($enrollment);
                $moduleProgress->setModule($module);
                $this->entityManager->persist($moduleProgress);
            }
        }

        // Flush pour sauvegarder les nouveaux ModuleProgress
        $this->entityManager->flush();
    }
}
