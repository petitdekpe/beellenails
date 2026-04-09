<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\Rendezvous;
use App\Form\RendezvousType;
use App\Repository\CreneauRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CalendarController extends AbstractController
{
    #[Route('/prendrerdv/{prestationId?}/{categoryId?}', name: 'app_calendar')]
    #/[IsGranted("ROLE_USER")]
    public function index(Request $request, EntityManagerInterface $entityManager, LoggerInterface $logger): Response
    {
        $clientIp = $request->getClientIp();
        $prestationId = $request->attributes->get('prestationId');
        $categoryId = $request->attributes->get('categoryId');

        $logger->info('[PrendreRdv] Accès à la page de prise de rendez-vous', [
            'ip' => $clientIp,
            'prestation_id' => $prestationId,
            'category_id' => $categoryId,
            'user_agent' => $request->headers->get('User-Agent'),
        ]);

        $rendezvous = new Rendezvous();
        //$rendezvous->setStatus("Validé");
        $form = $this->createForm(RendezvousType::class, $rendezvous);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logger->info('[PrendreRdv] Formulaire soumis et valide', [
                'ip' => $clientIp,
            ]);

            $formData = $form->getData();

            // Vérifier que le créneau appartient bien à la date sélectionnée
            $creneauRepository = $entityManager->getRepository(\App\Entity\Creneau::class);
            $availableSlots = $creneauRepository->findAvailableSlots($formData->getDay());
            $isSlotValid = false;

            foreach ($availableSlots as $slot) {
                if ($slot->getId() === $formData->getCreneau()->getId()) {
                    $isSlotValid = true;
                    break;
                }
            }

            if (!$isSlotValid) {
                $logger->warning('[PrendreRdv] Créneau invalide pour la date sélectionnée', [
                    'ip' => $clientIp,
                    'date' => $formData->getDay()?->format('Y-m-d'),
                    'creneau_id' => $formData->getCreneau()?->getId(),
                    'creneau_libelle' => $formData->getCreneau()?->getLibelle(),
                ]);
                $this->addFlash('error', 'Le créneau sélectionné n\'est pas disponible pour cette date.');
                return $this->render('calendar/index.html.twig', [
                    'controller_name' => 'CalendarController',
                    'rendezvous' => $rendezvous,
                    'form' => $form,
                ]);
            }

            $logger->debug('[PrendreRdv] Validation du créneau réussie', [
                'date' => $formData->getDay()?->format('Y-m-d'),
                'creneau_id' => $formData->getCreneau()?->getId(),
            ]);

            // Vérifier si le créneau est déjà en congé (double vérification)
            $existingConge = $entityManager->getRepository(Rendezvous::class)->findOneBy([
                'day' => $formData->getDay(),
                'creneau' => $formData->getCreneau(),
                'status' => 'Congé'
            ]);

            if ($existingConge) {
                $logger->warning('[PrendreRdv] Créneau indisponible (en congé)', [
                    'ip' => $clientIp,
                    'date' => $formData->getDay()?->format('Y-m-d'),
                    'creneau_id' => $formData->getCreneau()?->getId(),
                    'creneau_libelle' => $formData->getCreneau()?->getLibelle(),
                    'conge_id' => $existingConge->getId(),
                ]);
                $this->addFlash('error', 'Ce créneau est indisponible (en congé).');
                return $this->render('calendar/index.html.twig', [
                    'controller_name' => 'CalendarController',
                    'rendezvous' => $rendezvous,
                    'form' => $form,
                ]);
            }

            $request->getSession()->set('day',  $form->get('day')->getData());
            $request->getSession()->set('creneau',  $form->get('creneau')->getData());
            $request->getSession()->set('prestation',  $form->get('prestation')->getData());

            $rendezvous->setStatus("Tentative");

            $entityManager->persist($rendezvous);
            $entityManager->flush();

            $logger->info('[PrendreRdv] Rendez-vous créé avec succès (statut Tentative)', [
                'ip' => $clientIp,
                'rendezvous_id' => $rendezvous->getId(),
                'date' => $formData->getDay()?->format('Y-m-d'),
                'creneau_id' => $formData->getCreneau()?->getId(),
                'creneau_libelle' => $formData->getCreneau()?->getLibelle(),
                'prestation_id' => $formData->getPrestation()?->getId(),
                'prestation_title' => $formData->getPrestation()?->getTitle(),
            ]);

            return $this->redirectToRoute('app_recap', ['rendezvous' => $rendezvous->getId()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            $logger->warning('[PrendreRdv] Formulaire soumis avec des erreurs de validation', [
                'ip' => $clientIp,
                'errors' => $errors,
            ]);
        }

        return $this->render('calendar/index.html.twig', [
            'controller_name' => 'CalendarController',
            'rendezvous' => $rendezvous,
            'form' => $form,
        ]);
    }
    
    
    public function getAvailableSlots(Request $request, CreneauRepository $creneauRepository, LoggerInterface $logger): JsonResponse
    {
        $clientIp = $request->getClientIp();
        $dateParam = $request->request->get('date');

        $logger->debug('[PrendreRdv] Requête de créneaux disponibles', [
            'ip' => $clientIp,
            'date_requested' => $dateParam,
        ]);

        // Récupère la date sélectionnée depuis la requête
        $selectedDate = \DateTime::createFromFormat('Y-m-d', $dateParam);

        if (!$selectedDate) {
            $logger->warning('[PrendreRdv] Format de date invalide pour la récupération des créneaux', [
                'ip' => $clientIp,
                'date_received' => $dateParam,
            ]);
            return new JsonResponse(['error' => 'Format de date invalide'], 400);
        }

        try {
            // Utilise la méthode personnalisée du repository pour récupérer les créneaux disponibles
            $availableSlots = $creneauRepository->findAvailableSlots($selectedDate);

            // Formate les créneaux disponibles pour les envoyer en réponse
            $formattedSlots = [];
            foreach ($availableSlots as $slot) {
                $formattedSlots[] = ['id' => $slot->getId(), 'libelle' => $slot->getStartTime()->format('H:i'), 'sort' => $slot->getStartTime()->format('H:i:s')];
            }
            usort($formattedSlots, fn($a, $b) => strcmp($a['sort'], $b['sort']));

            $logger->info('[PrendreRdv] Créneaux disponibles récupérés avec succès', [
                'ip' => $clientIp,
                'date' => $selectedDate->format('Y-m-d'),
                'slots_count' => count($formattedSlots),
            ]);

            return new JsonResponse($formattedSlots);
        } catch (\Exception $e) {
            $logger->error('[PrendreRdv] Erreur lors de la récupération des créneaux disponibles', [
                'ip' => $clientIp,
                'date' => $dateParam,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return new JsonResponse(['error' => 'Erreur lors de la récupération des créneaux'], 500);
        }
    }

    #[Route('/api/log-client-error', name: 'api_log_client_error', methods: ['POST'])]
    public function logClientError(Request $request, LoggerInterface $logger): JsonResponse
    {
        $clientIp = $request->getClientIp();
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid data'], 400);
        }

        $type = $data['type'] ?? 'unknown';
        $message = $data['message'] ?? 'No message';
        $context = [
            'ip' => $clientIp,
            'user_agent' => $request->headers->get('User-Agent'),
            'page' => $data['page'] ?? 'unknown',
            'timestamp' => $data['timestamp'] ?? date('Y-m-d H:i:s'),
        ];

        // Ajouter les détails spécifiques selon le type d'erreur
        if (isset($data['stack'])) {
            $context['stack'] = $data['stack'];
        }
        if (isset($data['url'])) {
            $context['url'] = $data['url'];
        }
        if (isset($data['status'])) {
            $context['status'] = $data['status'];
        }
        if (isset($data['field'])) {
            $context['field'] = $data['field'];
        }
        if (isset($data['value'])) {
            $context['value'] = $data['value'];
        }
        if (isset($data['details'])) {
            $context['details'] = $data['details'];
        }

        // Logger selon le type
        switch ($type) {
            case 'js_error':
                $logger->error('[PrendreRdv][JS] Erreur JavaScript: ' . $message, $context);
                break;
            case 'ajax_error':
                $logger->error('[PrendreRdv][AJAX] Erreur requête AJAX: ' . $message, $context);
                break;
            case 'validation_error':
                $logger->warning('[PrendreRdv][Validation] Erreur de validation côté client: ' . $message, $context);
                break;
            case 'network_error':
                $logger->warning('[PrendreRdv][Network] Erreur réseau: ' . $message, $context);
                break;
            case 'timeout':
                $logger->warning('[PrendreRdv][Timeout] Timeout de requête: ' . $message, $context);
                break;
            case 'form_submit_blocked':
                $logger->warning('[PrendreRdv][Form] Soumission du formulaire bloquée: ' . $message, $context);
                break;
            default:
                $logger->warning('[PrendreRdv][Client] ' . $message, $context);
        }

        return new JsonResponse(['status' => 'logged']);
    }
}