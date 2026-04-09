<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\Creneau;
use App\Repository\CreneauRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/dashboard/creneaux')]
class DashboardCreneauController extends AbstractController
{
    #[Route('', name: 'app_dashboard_creneaux', methods: ['GET'])]
    public function index(CreneauRepository $creneauRepository): Response
    {
        $creneaux = $creneauRepository->findBy([], ['startTime' => 'ASC']);

        return $this->render('dashboard/creneau/index.html.twig', [
            'creneaux' => $creneaux,
        ]);
    }

    #[Route('/new', name: 'app_dashboard_creneau_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $em, CreneauRepository $creneauRepository): Response
    {
        if (!$this->isCsrfTokenValid('creneau_new', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_dashboard_creneaux');
        }

        $startTime = $request->request->get('startTime');
        $endTime   = $request->request->get('endTime');

        if (!$startTime || !$endTime) {
            $this->addFlash('error', 'L\'heure de début et de fin sont obligatoires.');
            return $this->redirectToRoute('app_dashboard_creneaux');
        }

        $start = \DateTime::createFromFormat('H:i', $startTime);
        $end   = \DateTime::createFromFormat('H:i', $endTime);

        if (!$start || !$end) {
            $this->addFlash('error', 'Format d\'heure invalide.');
            return $this->redirectToRoute('app_dashboard_creneaux');
        }

        if ($end <= $start) {
            $this->addFlash('error', 'L\'heure de fin doit être après l\'heure de début.');
            return $this->redirectToRoute('app_dashboard_creneaux');
        }

        // Vérifier doublon
        $existing = $creneauRepository->findOneBy([
            'startTime' => $start,
            'endTime'   => $end,
        ]);
        if ($existing) {
            $this->addFlash('error', 'Ce créneau existe déjà.');
            return $this->redirectToRoute('app_dashboard_creneaux');
        }

        $libelle = $start->format('H:i') . ' - ' . $end->format('H:i');

        $creneau = new Creneau();
        $creneau->setStartTime($start);
        $creneau->setEndTime($end);
        $creneau->setLibelle($libelle);
        $creneau->setIsActive(true);

        $em->persist($creneau);
        $em->flush();

        $this->addFlash('success', 'Créneau ' . $libelle . ' créé avec succès.');
        return $this->redirectToRoute('app_dashboard_creneaux');
    }

    #[Route('/{id}/toggle', name: 'app_dashboard_creneau_toggle', methods: ['POST'])]
    public function toggle(Request $request, Creneau $creneau, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('creneau_toggle_' . $creneau->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_dashboard_creneaux');
        }

        $creneau->setIsActive(!$creneau->isActive());
        $em->flush();

        $etat = $creneau->isActive() ? 'activé' : 'désactivé';
        $this->addFlash('success', 'Créneau ' . $creneau->getLibelle() . ' ' . $etat . '.');

        return $this->redirectToRoute('app_dashboard_creneaux');
    }
}
