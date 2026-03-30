<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\User;
use App\Entity\Rendezvous;
use App\Form\AdminAddRdvType;
use App\Form\RegistrationFormType;
use App\Form\RendezvousModifyType;
use App\Repository\UserRepository;
use App\Repository\RendezvousRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Service\PromoCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted("ROLE_ADMIN")]
#[Route('/mobile')]
class MobileController extends AbstractController
{
    // Agenda (calendrier)
    #[Route('', name: 'app_mobile_agenda')]
    public function agenda(RendezvousRepository $rendezvousRepository): Response
    {
        $rendezvousList = $rendezvousRepository->findPaidRendezvous();

        $events = [];
        foreach ($rendezvousList as $rendezvous) {
            $startDateTime = \DateTime::createFromFormat(
                'Y-m-d H:i:s',
                $rendezvous->getDay()->format('Y-m-d') . ' ' . $rendezvous->getCreneau()->getStartTime()->format('H:i:s')
            );
            $endDateTime = \DateTime::createFromFormat(
                'Y-m-d H:i:s',
                $rendezvous->getDay()->format('Y-m-d') . ' ' . $rendezvous->getCreneau()->getEndTime()->format('H:i:s')
            );

            $color = 'green';
            if ($rendezvous->getStatus() === 'Rendez-vous pris') {
                $color = 'orange';
            }

            $title = 'Utilisateur inconnu';
            if ($rendezvous->getStatus() === 'Congé') {
                $title = 'Congé';
                $color = 'red';
            } else {
                $title = $rendezvous->getUser()?->getFullName() ?? 'Utilisateur inconnu';
            }

            $events[] = [
                'id'         => $rendezvous->getId(),
                'title'      => $title,
                'prestation' => $rendezvous->getPrestation()->getTitle(),
                'start'      => $startDateTime->format('Y-m-d H:i:s'),
                'end'        => $endDateTime->format('Y-m-d H:i:s'),
                'color'      => $color,
            ];
        }

        return $this->render('mobile/agenda.html.twig', [
            'eventsJson' => json_encode($events),
        ]);
    }

    // Liste des rendez-vous
    #[Route('/rendez-vous', name: 'app_mobile_rendezvous', methods: ['GET'])]
    public function rendezvous(Request $request, RendezvousRepository $rendezvousRepository, PaginatorInterface $paginator): Response
    {
        $search      = $request->query->get('search');
        $status      = $request->query->get('status');
        $rdvDateFrom = $request->query->get('rdv_date_from');
        $rdvDateTo   = $request->query->get('rdv_date_to');

        $queryBuilder = $rendezvousRepository->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->orderBy('r.updated_at', 'DESC');

        if ($search) {
            $queryBuilder->andWhere(
                'LOWER(u.Nom) LIKE LOWER(:search) OR ' .
                'LOWER(u.Prenom) LIKE LOWER(:search) OR ' .
                'LOWER(CONCAT(u.Prenom, \' \', u.Nom)) LIKE LOWER(:search) OR ' .
                'LOWER(CONCAT(u.Nom, \' \', u.Prenom)) LIKE LOWER(:search)'
            )->setParameter('search', '%' . trim($search) . '%');
        }

        if ($status && $status !== 'all') {
            $queryBuilder->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }

        // Filtrer les congés par défaut
        if (!$status || $status === 'all') {
            $queryBuilder->andWhere('r.status != :conge')
                ->setParameter('conge', 'Congé');
        }

        if ($rdvDateFrom && $rdvDateTo) {
            $queryBuilder->andWhere('r.day >= :rdvDateFrom AND r.day <= :rdvDateTo')
                ->setParameter('rdvDateFrom', new \DateTime($rdvDateFrom . ' 00:00:00'))
                ->setParameter('rdvDateTo',   new \DateTime($rdvDateTo   . ' 23:59:59'));
        } elseif ($rdvDateFrom) {
            $queryBuilder->andWhere('r.day >= :rdvDateFrom AND r.day <= :rdvDateFromEnd')
                ->setParameter('rdvDateFrom',    new \DateTime($rdvDateFrom . ' 00:00:00'))
                ->setParameter('rdvDateFromEnd', new \DateTime($rdvDateFrom . ' 23:59:59'));
        } elseif ($rdvDateTo) {
            $queryBuilder->andWhere('r.day <= :rdvDateTo')
                ->setParameter('rdvDateTo', new \DateTime($rdvDateTo . ' 23:59:59'));
        }

        $rendezvous = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            15
        );

        return $this->render('mobile/rendezvous.html.twig', [
            'rendezvous'   => $rendezvous,
            'search'       => $search,
            'status'       => $status,
            'rdv_date_from' => $rdvDateFrom,
            'rdv_date_to'   => $rdvDateTo,
        ]);
    }

    // Ajouter un rendez-vous
    #[Route('/rdv/add', name: 'app_mobile_rdv_add', methods: ['GET', 'POST'])]
    public function addRdv(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        RendezvousRepository $rendezvousRepository,
        PromoCodeService $promoCodeService,
        LoggerInterface $logger
    ): Response {
        $rendezvous = new Rendezvous();
        $rendezvous->setImageName('default.png');
        $form = $this->createForm(AdminAddRdvType::class, $rendezvous, [
            'validation_groups' => ['admin'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier disponibilité du créneau
            $creneauRepository = $entityManager->getRepository(\App\Entity\Creneau::class);
            $availableSlots = $creneauRepository->findAvailableSlots($rendezvous->getDay());
            $isSlotValid = false;
            foreach ($availableSlots as $slot) {
                if ($slot->getId() === $rendezvous->getCreneau()->getId()) {
                    $isSlotValid = true;
                    break;
                }
            }

            if (!$isSlotValid) {
                $this->addFlash('error', 'Le créneau sélectionné n\'est pas disponible pour cette date.');
                return $this->render('mobile/rdv_add.html.twig', ['form' => $form->createView()]);
            }

            // Vérifier doublon
            $existing = $rendezvousRepository->findOneBy([
                'day'     => $rendezvous->getDay(),
                'creneau' => $rendezvous->getCreneau(),
                'status'  => 'Rendez-vous pris',
            ]);
            if ($existing) {
                $this->addFlash('warning', 'Un rendez-vous pour ce jour et créneau existe déjà.');
                return $this->render('mobile/rdv_add.html.twig', ['form' => $form->createView()]);
            }

            // Vérifier congé
            $existingConge = $rendezvousRepository->findOneBy([
                'day'     => $rendezvous->getDay(),
                'creneau' => $rendezvous->getCreneau(),
                'status'  => 'Congé',
            ]);
            if ($existingConge) {
                $this->addFlash('error', 'Ce créneau est indisponible (en congé).');
                return $this->render('mobile/rdv_add.html.twig', ['form' => $form->createView()]);
            }

            $rendezvous->setStatus('Rendez-vous confirmé');
            $rendezvous->setImageName('default.png');
            $rendezvous->setPaid('1');
            $rendezvous->updateTotalCost();

            if ($rendezvous->getPendingPromoCode()) {
                $promoCodeService->applyPendingPromoCode($rendezvous);
            }

            $entityManager->persist($rendezvous);
            $entityManager->flush();

            // Email confirmation
            try {
                $email = (new Email())
                    ->from('BeElle Nails Care <reservation@beellegroup.com>')
                    ->to($rendezvous->getUser()->getEmail())
                    ->replyTo('reservation@beellegroup.com')
                    ->subject('Informations de rendez-vous!')
                    ->html($this->renderView('emails/rendezvous_created.html.twig', ['rendezvous' => $rendezvous]));
                $email->getHeaders()
                    ->addTextHeader('X-Mailer', 'BeElle Nails Booking System')
                    ->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');
                $mailer->send($email);
            } catch (\Exception $e) {
                $logger->warning('[Mobile] Erreur envoi email RDV: ' . $e->getMessage());
            }

            $this->addFlash('success', 'Rendez-vous créé avec succès !');
            return $this->redirectToRoute('app_mobile_rendezvous');
        }

        return $this->render('mobile/rdv_add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // Modifier un rendez-vous
    #[Route('/rdv/{id}/edit', name: 'app_mobile_rdv_edit', methods: ['GET', 'POST'])]
    public function editRdv(
        Request $request,
        Rendezvous $rendezvous,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        $originalDay     = $rendezvous->getDay();
        $originalCreneau = $rendezvous->getCreneau();

        $form = $this->createForm(RendezvousModifyType::class, $rendezvous, [
            'validation_groups' => ['admin'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rendezvous->setPreviousDay($originalDay);
            $rendezvous->setPreviousCreneau($originalCreneau);
            $entityManager->flush();

            // Email client
            try {
                $emailClient = (new Email())
                    ->from('BeElle Nails Care <reservation@beellegroup.com>')
                    ->to($rendezvous->getUser()->getEmail())
                    ->replyTo('reservation@beellegroup.com')
                    ->subject('Votre Rendez-vous !')
                    ->html($this->renderView('emails/rendezvous_updated.html.twig', ['rendezvous' => $rendezvous]));
                $emailClient->getHeaders()
                    ->addTextHeader('X-Mailer', 'BeElle Nails Booking System')
                    ->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');
                $mailer->send($emailClient);

                // Email admin
                $emailAdmin = (new Email())
                    ->from('BeElle Nails Care <reservation@beellegroup.com>')
                    ->to('murielahodode@gmail.com', 'resabeelle@gmail.com')
                    ->bcc('petitdekpe@gmail.com')
                    ->replyTo('reservation@beellegroup.com')
                    ->subject('Rendez-vous modifié')
                    ->html($this->renderView('emails/rendezvous_updated_admin.html.twig', ['rendezvous' => $rendezvous]));
                $emailAdmin->getHeaders()
                    ->addTextHeader('X-Mailer', 'BeElle Nails Booking System')
                    ->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');
                $mailer->send($emailAdmin);
            } catch (\Exception $e) {
                // Log silencieux, ne pas bloquer la modification
            }

            $this->addFlash('success', 'Rendez-vous modifié avec succès !');
            return $this->redirectToRoute('app_mobile_rendezvous');
        }

        return $this->render('mobile/rdv_edit.html.twig', [
            'rendezvous' => $rendezvous,
            'form'       => $form->createView(),
        ]);
    }

    // Liste des clients
    #[Route('/clients', name: 'app_mobile_clients', methods: ['GET'])]
    public function clients(Request $request, UserRepository $userRepository, PaginatorInterface $paginator, RendezvousRepository $rendezvousRepository): Response
    {
        $search = $request->query->get('search');

        $queryBuilder = $userRepository->createQueryBuilder('u');
        if ($search) {
            $queryBuilder->andWhere(
                'u.Nom LIKE :search OR u.Prenom LIKE :search OR u.email LIKE :search OR u.Phone LIKE :search'
            )->setParameter('search', '%' . $search . '%');
        }
        $queryBuilder->orderBy('u.Nom', 'ASC');

        $users = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('mobile/clients.html.twig', [
            'users'  => $users,
            'search' => $search,
        ]);
    }

    // Ajouter un client
    #[Route('/clients/new', name: 'app_mobile_client_add', methods: ['GET', 'POST'])]
    public function clientAdd(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $userPasswordHasher,
        MailerInterface $mailer,
        LoggerInterface $logger
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager->persist($user);
            $entityManager->flush();

            try {
                $email = (new Email())
                    ->from('BeElle Nails Care <reservation@beellegroup.com>')
                    ->to($user->getEmail())
                    ->replyTo('reservation@beellegroup.com')
                    ->subject('Votre inscription sur BeElleNails')
                    ->html($this->renderView('registration/email.html.twig', ['user' => $user]));
                $email->getHeaders()
                    ->addTextHeader('X-Mailer', 'BeElle Nails Booking System')
                    ->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');
                $mailer->send($email);
            } catch (\Exception $e) {
                $logger->warning('[Mobile] Erreur envoi email inscription: ' . $e->getMessage());
            }

            $this->addFlash('success', 'Client créé avec succès !');
            return $this->redirectToRoute('app_mobile_clients');
        }

        return $this->render('mobile/client_add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // Détails d'un client
    #[Route('/clients/{id}', name: 'app_mobile_client_show', methods: ['GET'])]
    public function clientShow(User $user, RendezvousRepository $rendezvousRepository): Response
    {
        $rendezvous = $rendezvousRepository->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.status != :conge')
            ->setParameter('user', $user)
            ->setParameter('conge', 'Congé')
            ->orderBy('r.day', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('mobile/client_show.html.twig', [
            'user'       => $user,
            'rendezvous' => $rendezvous,
        ]);
    }
}
