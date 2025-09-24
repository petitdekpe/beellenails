<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Controller;

use App\Entity\User;
use App\Form\SearchType;
use App\Model\SearchData;
use App\Entity\Prestation;
use App\Entity\Rendezvous;
use App\Form\DateCongeType;
use App\Form\PrestationType;
use App\Form\AdminAddRdvType;
use App\Form\PeriodCongeType;
use Symfony\Component\Mime\Email;
use App\Form\RegistrationFormType;
use App\Form\RendezvousModifyType;
use App\Repository\UserRepository;
use App\Security\AppAuthenticator;
use App\Repository\CreneauRepository;
use App\Repository\PaymentRepository;
use App\Repository\FormationRepository;
use App\Repository\PrestationRepository;
use App\Repository\RendezvousRepository;
use App\Service\PromoCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Knp\Component\Pager\PaginatorInterface;

#[IsGranted("ROLE_ADMIN")]
class DashboardController extends AbstractController
{
    //Liste des rendez-vous dans le calendriers sous forme d'évènements
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(RendezvousRepository $rendezvousRepository): Response
    {

        // Exemple de récupération des rendez-vous depuis la base de données
        // Pas besoin de répéter l'injection de dépendance pour le repository
        // $rendezvousRepository = $this->getDoctrine()->getRepository(Rendezvous::class);
        $rendezvousList = $rendezvousRepository->findPaidRendezvous();

        // Initialiser un tableau pour stocker les événements
        $events = [];

        // Boucler à travers les rendez-vous et les formatter en tant qu'événements
        foreach ($rendezvousList as $rendezvous) {
            $startDateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $rendezvous->getDay()->format('Y-m-d') . ' ' . $rendezvous->getCreneau()->getStartTime()->format('H:i:s'));
            $endDateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $rendezvous->getDay()->format('Y-m-d') . ' ' . $rendezvous->getCreneau()->getEndTime()->format('H:i:s'));

            $color = 'green'; // Couleur par défaut

            // Si le statut du rendez-vous est "Rendez-vous pris", définissez la couleur sur rouge
            if ($rendezvous->getStatus() === 'Rendez-vous pris') {
                $color = 'orange';
            }

            $title = ''; // Initialisez le titre à une chaîne vide par défaut

            // Vérifiez si le statut du rendez-vous est "Congé"
            if ($rendezvous->getStatus() === 'Congé') {
                $title = 'Congé';
                $color = 'red'; // Définir le titre à "Congé" si le statut est "Congé"
            } else {
                // Utilisez le titre existant (nom et prénom de l'utilisateur)
                //$title = $rendezvous->getUser()->getNom() . ' ' . $rendezvous->getUser()->getPrenom();
                $title = $rendezvous->getUser()?->getFullName() ?? 'Utilisateur inconnu';
            }

            $event = [
                'id' => $rendezvous->getId(),
                'title' => $title,
                'prestation' => $rendezvous->getPrestation()->getTitle(),
                'start' => $startDateTime->format('Y-m-d H:i:s'),
                'end' => $endDateTime->format('Y-m-d H:i:s'), // Ajoutez cet attribut si vous avez une date de fin
                'image' => $rendezvous->getImageName(),
                'color' => $color, // Définir la couleur de l'événement
                // Autres champs de rendez-vous à ajouter en tant que propriétés d'événement
            ];

            // Ajouter l'événement au tableau des événements
            $events[] = $event;
        }

        // Convertir les événements en format JSON
        $eventsJson = json_encode($events);


        return $this->render('dashboard/index.html.twig', [
            'controller_name' => 'DashboardController',
            'eventsJson' => $eventsJson, // Passer les événements à la vue
        ]);
    }

    //Liste des prestations
    #[Route('/dashboard/prestation', name: 'app_dashboard_prestation', methods: ['GET'])]
    public function prestation(Request $request, PrestationRepository $prestationRepository): Response
    {
        $search = $request->query->get('search');

        if ($search) {
            $prestations = $prestationRepository->createQueryBuilder('p')
                ->where('p.Title LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->orderBy('p.Title', 'ASC')
                ->getQuery()
                ->getResult();
        } else {
            $prestations = $prestationRepository->findBy([], ['Title' => 'ASC']);
        }

        return $this->render('dashboard/prestation.html.twig', [
            'prestations' => $prestations,
            'search' => $search,
        ]);
    }
    //Liste des rendez-vous (order by updated_at)
    #[Route('/dashboard/rendezvous', name: 'app_dashboard_rendezvous', methods: ['GET'])]
    public function rendezvous(Request $request, RendezvousRepository $rendezvousRepository, PaginatorInterface $paginator): Response
    {
        $search = $request->query->get('search');
        $status = $request->query->get('status');
        $rdvDateFrom = $request->query->get('rdv_date_from');
        $rdvDateTo = $request->query->get('rdv_date_to');
        $modifiedFrom = $request->query->get('modified_from');
        $modifiedTo = $request->query->get('modified_to');

        $queryBuilder = $rendezvousRepository->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->leftJoin('r.prestation', 'p')
            ->orderBy('r.updated_at', 'DESC');

        // Recherche par nom du client (supporte nom + prénom)
        if ($search) {
            $queryBuilder->andWhere(
                'LOWER(u.Nom) LIKE LOWER(:search) OR ' .
                    'LOWER(u.Prenom) LIKE LOWER(:search) OR ' .
                    'LOWER(CONCAT(u.Prenom, \' \', u.Nom)) LIKE LOWER(:search) OR ' .
                    'LOWER(CONCAT(u.Nom, \' \', u.Prenom)) LIKE LOWER(:search)'
            )->setParameter('search', '%' . trim($search) . '%');
        }

        // Filtre par statut
        if ($status && $status !== 'all') {
            $queryBuilder->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }

        // Filtre par date de rendez-vous
        if ($rdvDateFrom && $rdvDateTo) {
            // Plage de dates
            $queryBuilder->andWhere('r.day >= :rdvDateFrom AND r.day <= :rdvDateTo')
                ->setParameter('rdvDateFrom', new \DateTime($rdvDateFrom . ' 00:00:00'))
                ->setParameter('rdvDateTo', new \DateTime($rdvDateTo . ' 23:59:59'));
        } elseif ($rdvDateFrom) {
            // Date précise uniquement
            $queryBuilder->andWhere('r.day >= :rdvDateFrom AND r.day <= :rdvDateFromEnd')
                ->setParameter('rdvDateFrom', new \DateTime($rdvDateFrom . ' 00:00:00'))
                ->setParameter('rdvDateFromEnd', new \DateTime($rdvDateFrom . ' 23:59:59'));
        } elseif ($rdvDateTo) {
            // Jusqu'à cette date
            $queryBuilder->andWhere('r.day <= :rdvDateTo')
                ->setParameter('rdvDateTo', new \DateTime($rdvDateTo . ' 23:59:59'));
        }

        // Filtre par date de dernière modification
        if ($modifiedFrom) {
            $queryBuilder->andWhere('r.updated_at >= :modifiedFrom')
                ->setParameter('modifiedFrom', new \DateTime($modifiedFrom . ' 00:00:00'));
        }

        if ($modifiedTo) {
            $queryBuilder->andWhere('r.updated_at <= :modifiedTo')
                ->setParameter('modifiedTo', new \DateTime($modifiedTo . ' 23:59:59'));
        }

        $rendezvous = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            15 // Nombre d'éléments par page
        );

        return $this->render('dashboard/rendezvous.html.twig', [
            'rendezvous' => $rendezvous,
            'search' => $search,
            'status' => $status,
            'rdv_date_from' => $rdvDateFrom,
            'rdv_date_to' => $rdvDateTo,
            'modified_from' => $modifiedFrom,
            'modified_to' => $modifiedTo,
        ]);
    }

    //Liste des clients
    #[Route('/dashboard/clients', name: 'app_dashboard_user', methods: ['GET'])]
    public function user(Request $request, UserRepository $userRepository, PaginatorInterface $paginator, RendezvousRepository $rendezvousRepository): Response
    {
        $search = $request->query->get('search');
        $sortBy = $request->query->get('sort', 'name'); // Par défaut tri par nom

        // Récupérer tous les utilisateurs avec filtres
        $queryBuilder = $userRepository->createQueryBuilder('u');
        
        // Recherche
        if ($search) {
            $queryBuilder->andWhere('u.Nom LIKE :search OR u.Prenom LIKE :search OR u.email LIKE :search OR u.Phone LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        $allUsers = $queryBuilder->getQuery()->getResult();
        
        // Ajouter le comptage des RDV à chaque utilisateur pour tous les tris
        foreach ($allUsers as $user) {
            $rdvCount = $rendezvousRepository->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->andWhere('r.user = :user')
                ->andWhere('r.status IN (:validStatuses)')
                ->setParameter('user', $user)
                ->setParameter('validStatuses', ['Rendez-vous pris', 'Rendez-vous confirmé'])
                ->getQuery()
                ->getSingleScalarResult();
                
            $user->rdvCount = $rdvCount;
        }
        
        // Trier en PHP selon le critère choisi
        switch ($sortBy) {
            case 'rdv_count_desc':
                usort($allUsers, function($a, $b) {
                    if ($a->rdvCount == $b->rdvCount) {
                        return strcmp($a->getNom(), $b->getNom());
                    }
                    return $b->rdvCount - $a->rdvCount;
                });
                break;
            case 'rdv_count_asc':
                usort($allUsers, function($a, $b) {
                    if ($a->rdvCount == $b->rdvCount) {
                        return strcmp($a->getNom(), $b->getNom());
                    }
                    return $a->rdvCount - $b->rdvCount;
                });
                break;
            case 'date_desc':
                usort($allUsers, function($a, $b) {
                    return $b->getCreatedAt() <=> $a->getCreatedAt();
                });
                break;
            case 'date_asc':
                usort($allUsers, function($a, $b) {
                    return $a->getCreatedAt() <=> $b->getCreatedAt();
                });
                break;
            default: // 'name'
                usort($allUsers, function($a, $b) {
                    $nameCompare = strcmp($a->getNom(), $b->getNom());
                    if ($nameCompare === 0) {
                        return strcmp($a->getPrenom(), $b->getPrenom());
                    }
                    return $nameCompare;
                });
                break;
        }
        
        // Pagination sur le tableau trié
        $users = $paginator->paginate(
            $allUsers,
            $request->query->getInt('page', 1),
            15
        );

        return $this->render('dashboard/user.html.twig', [
            'users' => $users,
            'search' => $search,
            'sort' => $sortBy,
        ]);
    }

    //Liste des transactions
    #[Route('/dashboard/transactions', name: 'app_dashboard_transactions', methods: ['GET'])]
    public function payment(Request $request, PaymentRepository $paymentRepository, PaginatorInterface $paginator): Response
    {
        $provider = $request->query->get('provider');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');

        $queryBuilder = $paymentRepository->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC');

        // Filtre par provider
        if ($provider && $provider !== 'all') {
            $queryBuilder->andWhere('p.provider = :provider')
                ->setParameter('provider', $provider);
        }

        // Filtre par date
        if ($dateFrom) {
            $queryBuilder->andWhere('p.createdAt >= :dateFrom')
                ->setParameter('dateFrom', new \DateTime($dateFrom . ' 00:00:00'));
        }

        if ($dateTo) {
            $queryBuilder->andWhere('p.createdAt <= :dateTo')
                ->setParameter('dateTo', new \DateTime($dateTo . ' 23:59:59'));
        }

        $payments = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            20 // Nombre d'éléments par page
        );

        return $this->render('dashboard/payment.html.twig', [
            'payments' => $payments,
            'provider' => $provider,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
    }

    //Ajouter un rendez-vous
    #[Route('/dashboard/rendezvous/add', name: 'app_admin_rdv')]
    public function new(Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer, RendezvousRepository $rendezvousRepository, PromoCodeService $promoCodeService, LoggerInterface $logger): Response
    {
        $rendezvous = new Rendezvous();
        $form = $this->createForm(AdminAddRdvType::class, $rendezvous);
        $form->handleRequest($request);


        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $rendezvous;

            // Vérifier que le créneau appartient bien à la date sélectionnée (validation croisée)
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
                $this->addFlash('error', 'Le créneau sélectionné n\'est pas disponible pour cette date.');
                return $this->render('dashboard/rendezvous/add.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            // Vérifier si un rendez-vous avec le même jour et créneau existe déjà
            $existingRendezvous = $rendezvousRepository->findOneBy([
                'day' => $rendezvous->getDay(),
                'creneau' => $rendezvous->getCreneau(),
                'status' => 'Rendez-vous pris'
            ]);

            if ($existingRendezvous) {
                // Si un rendez-vous existe déjà, rediriger vers la page de prise de rendez-vous ou afficher un message d'erreur
                $this->addFlash('warning', 'Un rendez-vous pour ce jour et créneau existe déjà.');
                return $this->redirectToRoute('app_calendar');
            }

            // Vérifier si le créneau est déjà en congé (double vérification)
            $existingConge = $rendezvousRepository->findOneBy([
                'day' => $rendezvous->getDay(),
                'creneau' => $rendezvous->getCreneau(),
                'status' => 'Congé'
            ]);

            if ($existingConge) {
                $this->addFlash('error', 'Ce créneau est indisponible (en congé).');
                return $this->render('dashboard/rendezvous/add.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            // Continuer si aucun rendez-vous en conflit n'est trouvé
            $rendezvous->setStatus("Rendez-vous confirmé");
            $rendezvous->setImageName("default.png");
            $rendezvous->setPaid("1");

            // Calculer et enregistrer le coût total
            $rendezvous->updateTotalCost();

            // Appliquer le code promo en attente s'il y en a un
            if ($rendezvous->getPendingPromoCode()) {
                $result = $promoCodeService->applyPendingPromoCode($rendezvous);
                $logger->info("[Admin Creation] Code promo traité", [
                    'rendezvous_id' => $rendezvous->getId(),
                    'promo_result' => $result['isValid'] ? 'appliqué' : 'échoué',
                    'message' => $result['message']
                ]);
            }

            $entityManager->persist($rendezvous);
            $entityManager->flush();

            $userEmail = $rendezvous->getUser()->getEmail();

            $email = (new Email())
                ->from('beellenailscare@beellenails.com')
                ->to($userEmail)
                ->subject('Informations de rendez-vous!')
                ->html($this->renderView(
                    'emails/rendezvous_created.html.twig',
                    ['rendezvous' => $rendezvous]
                ));
            $mailer->send($email);

            return $this->redirectToRoute('app_dashboard_rendezvous', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/rendezvous/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    //Reporter un rendez-vous
    #[Route('/dashboard/rendezvous/{id}/edit', name: 'app_admin_rdv_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Rendezvous $rendezvous, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        // Sauvegarder les anciennes informations avant la modification
        $originalDay = $rendezvous->getDay();
        $originalCreneau = $rendezvous->getCreneau();
        
        // Création d'un formulaire personnalisé avec seulement les champs 'day' et 'creneau'
        $form = $this->createForm(RendezvousModifyType::class, $rendezvous);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Toujours sauvegarder l'historique lors d'une modification manuelle
            // Cela garantit que nous avons les anciennes informations même si
            // il y a eu des modifications précédentes non enregistrées
            $rendezvous->setPreviousDay($originalDay);
            $rendezvous->setPreviousCreneau($originalCreneau);
            
            // Persistance des changements en base de données
            $entityManager->flush();

            // Récupérer l'adresse e-mail de l'utilisateur à partir du rendez-vous
            $userEmail = $rendezvous->getUser()->getEmail();

            // Envoyer l'e-mail au client
            $email = (new Email())
                ->from('beellenailscare@beellenails.com')
                ->to($userEmail)
                ->subject('Votre Rendez-vous !')
                ->html($this->renderView(
                    'emails/rendezvous_updated.html.twig',
                    ['rendezvous' => $rendezvous]
                ));
            $mailer->send($email);

            // Envoyer l'e-mail à l'admin
            $adminEmail = (new Email())
                ->from('beellenailscare@beellenails.com')
                ->to('murielahodode@gmail.com')
                ->subject('Rendez-vous modifié')
                ->html($this->renderView(
                    'emails/rendezvous_updated_admin.html.twig',
                    ['rendezvous' => $rendezvous]
                ));
            $mailer->send($adminEmail);

            return $this->redirectToRoute('app_dashboard_rendezvous');
        }

        return $this->render('rendezvous/edit.html.twig', [
            'rendezvous' => $rendezvous,
            'form' => $form->createView(),
        ]);
    }

    //Annuler un rendez-vous
    #[Route('/dashboard/rendezvous/{id}/cancel', name: 'app_admin_rdv_cancel', methods: ['GET', 'POST'])]
    public function cancel(
        Request $request,
        Rendezvous $rendezvous,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        PromoCodeService $promoCodeService,
        LoggerInterface $logger
    ): Response {
        // Met à jour le statut
        $rendezvous->setStatus("Annulé");
        
        // Révoquer le code promo si il y en a un
        if ($rendezvous->getPromoCode()) {
            $result = $promoCodeService->revokePromoCodeUsage($rendezvous, 'Rendez-vous annulé par l\'admin');
            $logger->info("[Admin Cancellation] Code promo révoqué suite à l'annulation", [
                'rendezvous_id' => $rendezvous->getId(),
                'reason' => 'Rendez-vous annulé par l\'admin'
            ]);
        }
        
        $entityManager->persist($rendezvous);
        $entityManager->flush();

        // Email utilisateur
        $userEmail = $rendezvous->getUser()->getEmail();
        $emailClient = (new Email())
            ->from('beellenailscare@beellenails.com')
            ->to($userEmail)
            ->subject('Rendez-vous Annulé !')
            ->html($this->renderView(
                'emails/rendezvous_canceled.html.twig',
                ['rendezvous' => $rendezvous]
            ));
        $mailer->send($emailClient);



        return $this->redirectToRoute('app_dashboard_rendezvous');
    }



    //Ajouter un client
    #[Route('/dashboard/inscription', name: 'app_dashboard_add_user')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, AppAuthenticator $appAuthenticator, UserAuthenticatorInterface $userAuthenticator, MailerInterface $mailer): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager->persist($user);
            $entityManager->flush();
            // do anything else you need here, like send an email
            // Envoyer l'e-mail de création de compte
            $email = (new Email())
                ->from('beellenailscare@beellenails.com')
                ->to($user->getEmail())
                ->subject('Votre inscription sur BeElleNails')
                ->html($this->renderView(
                    'registration/email.html.twig',
                    ['user' => $user]
                ));

            $mailer->send($email);

            return $this->redirectToRoute('app_dashboard_user');
        }

        return $this->render('dashboard/user/adduser.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    //Modifier le statut d'un rendez-vous
    #[Route('/dashboard/rendezvous/{id}/confirm', name: 'app_admin_rdv_confirm', methods: ['GET', 'POST'])]
    public function confirm(Rendezvous $rendezvous, EntityManagerInterface $entityManager, MailerInterface $mailer, PromoCodeService $promoCodeService, LoggerInterface $logger): Response
    {
        // Modifier le statut du rendez-vous en "Rendez-vous confirmé"
        $rendezvous->setStatus('Rendez-vous confirmé');
        
        // Calculer et enregistrer le coût total avant application des codes promo
        $rendezvous->updateTotalCost();
        
        // Appliquer le code promo en attente s'il y en a un
        if ($rendezvous->getPendingPromoCode()) {
            $result = $promoCodeService->applyPendingPromoCode($rendezvous);
            $logger->info("[Admin Confirmation] Code promo traité", [
                'rendezvous_id' => $rendezvous->getId(),
                'promo_result' => $result['isValid'] ? 'appliqué' : 'échoué',
                'message' => $result['message']
            ]);
        }
        
        $entityManager->flush();
        // Redirection vers la liste des rendez-vous
        return $this->redirectToRoute('app_dashboard_rendezvous');
    }

    //Afficher la liste des congés dans le dashboard
    #[Route('/dashboard/conges', name: 'app_dashboard_conges', methods: ['GET'])]
    public function conges(RendezvousRepository $rendezvousRepository, EntityManagerInterface $entityManager): Response
    {
        // Récupérer tous les congés
        $congesQuery = $rendezvousRepository->createQueryBuilder('r')
            ->where('r.status = :status')
            ->setParameter('status', 'Congé')
            ->orderBy('r.day', 'ASC')
            ->addOrderBy('r.creneau', 'ASC')
            ->getQuery();

        $conges = $congesQuery->getResult();

        // Regrouper les congés par date
        $congesByDay = [];
        $creneauxByDay = [];

        foreach ($conges as $conge) {
            $dayKey = $conge->getDay()->format('Y-m-d');

            if (!isset($congesByDay[$dayKey])) {
                $congesByDay[$dayKey] = [];
                $creneauxByDay[$dayKey] = [];
            }

            $congesByDay[$dayKey][] = $conge;
            $creneauxByDay[$dayKey][] = $conge->getCreneau();
        }

        // Pour chaque jour, vérifier si tous les créneaux sont en congé
        $processedConges = [];

        // Récupérer tous les créneaux disponibles pour comparaison
        $allCreneaux = $entityManager->getRepository(\App\Entity\Creneau::class)->findAll();
        $totalCreneaux = count($allCreneaux);

        foreach ($congesByDay as $dayKey => $dayConges) {
            $day = new \DateTime($dayKey);
            $creneauxCount = count($dayConges);

            $processedConges[] = [
                'date' => $day,
                'isFullDay' => $creneauxCount >= $totalCreneaux,
                'conges' => $dayConges,
                'creneauxCount' => $creneauxCount
            ];
        }

        return $this->render('dashboard/conges_list.html.twig', [
            'processedConges' => $processedConges,
            'totalCreneaux' => $totalCreneaux
        ]);
    }

    //Afficher la liste des formations dans le dashboard
    #[Route('/dashboard/formation', name: 'app_dashboard_formation', methods: ['GET'])]
    public function formation(FormationRepository $formationRepository): Response
    {
        return $this->render('dashboard/formation.html.twig', [
            'formations' => $formationRepository->findAll(),
        ]);
    }

    //Prendre des congés (un jour)

    #[Route('dashboard/create-conge', name: 'create_conge')]
    public function createRendezvous(Request $request, EntityManagerInterface $entityManager, RendezvousRepository $rendezvousRepository, CreneauRepository $creneauRepository, PrestationRepository $prestationRepository): Response
    {
        $form = $this->createForm(DateCongeType::class, null, [
            'validation_groups' => ['Default', 'without_prestation']
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $date = $form->get('date')->getData();

            // Vérifier s'il y a des rendez-vous confirmés ou pris pour cette date
            $rendezvousExistants = $rendezvousRepository->findBy([
                'day' => $date,
                'status' => ['Rendez-vous confirmé', 'Rendez-vous pris', 'Congé']
            ]);

            if (!empty($rendezvousExistants)) {
                $this->addFlash('warning', 'Des rendez-vous sont déjà prévus pour cette date. Veuillez annuler les rendez-vous existants.');
                return $this->redirectToRoute('app_dashboard', ['message' => 'Des rendez-vous sont déjà prévus pour cette date. Veuillez annuler les rendez-vous existants.']);
            }

            // Récupérer la prestation avec l'ID 1
            $prestation = $prestationRepository->find(1);

            // Récupérer la liste des créneaux disponibles
            $creneaux = $creneauRepository->findAll();
            $user = $this->getUser(); // Récupérer l'utilisateur connecté

            foreach ($creneaux as $creneau) {
                // Créer un rendez-vous pour chaque créneau avec le statut "Congé"
                $rendezvous = new Rendezvous();
                $rendezvous->setDay($date);
                $rendezvous->setCreneau($creneau);
                $rendezvous->setStatus("Congé");
                $rendezvous->setImageName("default.png");
                $rendezvous->setUser($user);
                $rendezvous->setPrestation($prestation);

                $entityManager->persist($rendezvous);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Les rendez-vous ont été créés avec succès.');

            return $this->redirectToRoute('app_dashboard', ['message' => 'Les rendez-vous ont été créés avec succès.']);
        }

        return $this->render('dashboard/conge.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    //Prendre des congés (une période)

    #[Route('dashboard/create-conges', name: 'create_conges')]
    public function createConges(Request $request, EntityManagerInterface $entityManager, RendezvousRepository $rendezvousRepository, CreneauRepository $creneauRepository, PrestationRepository $prestationRepository): Response
    {
        $form = $this->createForm(PeriodCongeType::class, null, [
            'validation_groups' => ['Default', 'without_prestation']
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $startDate = $form->get('start_date')->getData();
            $endDate = $form->get('end_date')->getData();

            $period = new \DatePeriod(
                $startDate,
                new \DateInterval('P1D'),
                $endDate->modify('+1 day') // pour inclure la date de fin
            );

            foreach ($period as $date) {
                // Vérifier s'il y a des rendez-vous confirmés ou pris pour chaque date
                $rendezvousExistants = $rendezvousRepository->findBy([
                    'day' => $date,
                    'status' => ['Rendez-vous confirmé', 'Rendez-vous pris', 'Congé']
                ]);

                if (!empty($rendezvousExistants)) {
                    $this->addFlash('warning', "Des rendez-vous sont déjà prévus pour le {$date->format('d/m/Y')}. Veuillez annuler les rendez-vous existants.");
                    return $this->redirectToRoute('app_dashboard', ['message' => "Des rendez-vous sont déjà prévus pour le {$date->format('d/m/Y')}. Veuillez annuler les rendez-vous existants."]);
                }

                // Récupérer la prestation avec l'ID 1
                $prestation = $prestationRepository->find(1);

                // Récupérer la liste des créneaux disponibles
                $creneaux = $creneauRepository->findAll();
                $user = $this->getUser(); // Récupérer l'utilisateur connecté

                foreach ($creneaux as $creneau) {
                    // Créer un rendez-vous pour chaque créneau avec le statut "Congé"
                    $rendezvous = new Rendezvous();
                    $rendezvous->setDay($date);
                    $rendezvous->setCreneau($creneau);
                    $rendezvous->setStatus("Congé");
                    $rendezvous->setImageName("default.png");
                    $rendezvous->setUser($user);
                    $rendezvous->setPrestation($prestation);

                    $entityManager->persist($rendezvous);
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Les congés ont été créés avec succès.');

            return $this->redirectToRoute('app_dashboard', ['message' => 'Les congés ont été créés avec succès.']);
        }

        return $this->render('dashboard/conges.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    //Annuler des congés (un jour)
    #[Route('dashboard/annuler-conge', name: 'cancel_conge')]
    public function cancelConge(Request $request, EntityManagerInterface $entityManager, RendezvousRepository $rendezvousRepository): Response
    {
        $form = $this->createForm(DateCongeType::class, null, [
            'validation_groups' => ['Default', 'without_prestation']
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $date = $form->get('date')->getData();

            // Récupérer tous les congés pour cette date
            $congesExistants = $rendezvousRepository->findBy([
                'day' => $date,
                'status' => 'Congé'
            ]);

            if (empty($congesExistants)) {
                $this->addFlash('warning', 'Aucun congé trouvé pour cette date.');
                return $this->redirectToRoute('app_dashboard', ['message' => 'Aucun congé trouvé pour cette date.']);
            }

            // Supprimer tous les congés pour cette date
            foreach ($congesExistants as $conge) {
                $entityManager->remove($conge);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Le congé du ' . $date->format('d/m/Y') . ' a été annulé avec succès.');

            return $this->redirectToRoute('app_dashboard', ['message' => 'Le congé a été annulé avec succès.']);
        }

        return $this->render('dashboard/annuler_conge.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    //Annuler des congés (une période)
    #[Route('dashboard/annuler-conges', name: 'cancel_conges')]
    public function cancelConges(Request $request, EntityManagerInterface $entityManager, RendezvousRepository $rendezvousRepository): Response
    {
        $form = $this->createForm(PeriodCongeType::class, null, [
            'validation_groups' => ['Default', 'without_prestation']
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $startDate = $form->get('start_date')->getData();
            $endDate = $form->get('end_date')->getData();

            $period = new \DatePeriod(
                $startDate,
                new \DateInterval('P1D'),
                $endDate->modify('+1 day')
            );

            $totalCanceled = 0;

            foreach ($period as $date) {
                // Récupérer tous les congés pour cette date
                $congesExistants = $rendezvousRepository->findBy([
                    'day' => $date,
                    'status' => 'Congé'
                ]);

                // Supprimer tous les congés pour cette date
                foreach ($congesExistants as $conge) {
                    $entityManager->remove($conge);
                    $totalCanceled++;
                }
            }

            if ($totalCanceled === 0) {
                $this->addFlash('warning', 'Aucun congé trouvé pour cette période.');
                return $this->redirectToRoute('app_dashboard', ['message' => 'Aucun congé trouvé pour cette période.']);
            }

            $entityManager->flush();

            $this->addFlash('success', "Les congés de la période du {$startDate->format('d/m/Y')} au {$endDate->format('d/m/Y')} ont été annulés avec succès.");

            return $this->redirectToRoute('app_dashboard', ['message' => 'Les congés ont été annulés avec succès.']);
        }

        return $this->render('dashboard/annuler_conges.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    //Ajouter une prestation

    #[Route('dashboard/prestation/new', name: 'app_dashboard_prestation_new', methods: ['GET', 'POST'])]
    public function addprestation(Request $request, EntityManagerInterface $entityManager): Response
    {
        $prestation = new Prestation();
        $form = $this->createForm(PrestationType::class, $prestation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($prestation);
            $entityManager->flush();

            return $this->redirectToRoute('app_prestation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('prestation/new.html.twig', [
            'prestation' => $prestation,
            'form' => $form,
        ]);
    }
}
