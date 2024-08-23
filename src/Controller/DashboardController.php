<?php

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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class DashboardController extends AbstractController
{
    //Liste des rendez-vous dans le calendriers sous forme d'évènements
        #[Route('/dashboard', name: 'app_dashboard')]
        #[IsGranted("ROLE_ADMIN")]
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
                    $title = $rendezvous->getUser()->getNom() . ' ' . $rendezvous->getUser()->getPrenom();
                }

                $event = [
                    'id' => $rendezvous->getId(),
                    'title' => $title,
                    'prestation' => $rendezvous->getPrestation()->getTitle(),
                    'start' => $startDateTime->format('Y-m-d H:i:s'),
                    'end' => $endDateTime->format('Y-m-d H:i:s'), // Ajoutez cet attribut si vous avez une date de fin
                    'image'=>$rendezvous->getImageName(),
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
        public function prestation(PrestationRepository $prestationRepository): Response
        {
            return $this->render('dashboard/prestation.html.twig', [
                'prestations' => $prestationRepository->findAll(),
            ]);
        }
    //Liste des rendez-vous (order by updated_at)
        #[Route('/dashboard/rendezvous', name: 'app_dashboard_rendezvous', methods: ['GET'])]
        public function rendezvous(RendezvousRepository $rendezvousRepository, Request $request): Response
        {
            $searchData = new SearchData();
            $form = $this->createForm(SearchType::class, $searchData);

            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                // Ajoutez ici votre logique de recherche si nécessaire
            }

            $rendezvouses = $rendezvousRepository->findBy([], ['updated_at' => 'DESC']);

            // Grouper les rendez-vous par jour
            $rendezvousByDay = [];
            foreach ($rendezvouses as $rendezvous) {
                $day = $rendezvous->getUpdatedAt()->format('Y-m-d');
                if (!isset($rendezvousByDay[$day])) {
                    $rendezvousByDay[$day] = [];
                }
                $rendezvousByDay[$day][] = $rendezvous;
            }

            return $this->render('dashboard/rendezvous.html.twig', [
                //'form' => $form->createView(),
                'rendezvousByDay' => $rendezvousByDay,
            ]);
        }
        
    //Liste des clients
        #[Route('/dashboard/clients', name: 'app_dashboard_user', methods: ['GET'])]
        public function user(UserRepository $userRepository): Response
        {
            $users = $userRepository->findBy([], ['Nom' => 'ASC', 'Prenom' => 'ASC']);
        
            return $this->render('dashboard/user.html.twig', [
                'users' => $users,
            ]);
        }

    //Liste des transactions
        #[Route('/dashboard/transactions', name: 'app_dashboard_transactions', methods: ['GET'])]
        public function payment(PaymentRepository $paymentRepository): Response
        {
            return $this->render('dashboard/payment.html.twig', [
                //'payments' => $paymentRepository->findAll(),
                'payments' => $paymentRepository->findBy([], ['updatedAt' => 'DESC']),
            ]);
        }

    //Ajouter un rendez-vous
        #[Route('/dashboard/rendezvous/add', name: 'app_admin_rdv')]
        public function new(Request $request, EntityManagerInterface $entityManager, MailerInterface$mailer, RendezvousRepository $rendezvousRepository): Response
        {
            $rendezvous = new Rendezvous();
            $form = $this->createForm(AdminAddRdvType::class, $rendezvous);
            $form->handleRequest($request);
            

            if ($form->isSubmitted() && $form->isValid()) {
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

        // Continuer si aucun rendez-vous en conflit n'est trouvé
                $rendezvous->setStatus("Rendez-vous confirmé");
                $rendezvous->setImageName("default.png");
                $rendezvous->setPaid("1");


                $entityManager->persist($rendezvous);
                $entityManager->flush();

                $userEmail = $rendezvous->getUser()->getEmail();

                $email = (new Email())
                                ->from('beellenailscare@beellenails.com')
                                ->to($userEmail)
                                ->subject('Informations de rendez-vous!')
                                ->html($this->renderView(
                                    'emails/rendezvous_created.html.twig',
                                    ['rendezvou' => $rendezvous]
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
        public function edit(Request $request, Rendezvous $rendezvou, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
        {
            // Création d'un formulaire personnalisé avec seulement les champs 'day' et 'creneau'
            $form = $this->createForm(RendezvousModifyType::class, $rendezvou);
            $form->handleRequest($request);
        
            if ($form->isSubmitted()) {
                // Persistance des changements en base de données
                $entityManager->flush();

                // Récupérer l'adresse e-mail de l'utilisateur à partir du rendez-vous
            $userEmail = $rendezvou->getUser()->getEmail();

            // Envoyer l'e-mail après la création du rendez-vous
            $email = (new Email())
            ->from('beellenailscare@beellenails.com')
            ->to($userEmail)
            ->subject('Votre Rendez-vous !')
            ->html($this->renderView(
                'emails/rendezvous_updated.html.twig',
                ['rendezvous' => $rendezvou]
            ));
            $mailer->send($email);
        
                return $this->redirectToRoute('app_dashboard_rendezvous');
            }
        
            return $this->render('rendezvous/edit.html.twig', [
                'rendezvou' => $rendezvou,
                'form' => $form->createView(),
            ]);
        }

    //Annuler un rendez-vous
        #[Route('/dashboard/rendezvous/{id}/cancel', name: 'app_admin_rdv_cancel', methods: ['GET', 'POST'])]
        public function cancel(Request $request, Rendezvous $rendezvou, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
        {
            $rendezvou->setStatus("Annulé");

            $entityManager->persist($rendezvou);
            $entityManager->flush();

            // Récupérer l'adresse e-mail de l'utilisateur à partir du rendez-vous
            $userEmail = $rendezvou->getUser()->getEmail();

            // Envoyer l'e-mail après la création du rendez-vous
            $email = (new Email())
            ->from('beellenailscare@beellenails.com')
            ->to($userEmail)
            ->subject('Rendez-vous Annulé !')
            ->html($this->renderView(
                'emails/rendezvous_canceled.html.twig',
                ['rendezvous' => $rendezvou]
            ));

        $mailer->send($email);

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
            public function confirm(Rendezvous $rendezvous, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
            {
                // Modifier le statut du rendez-vous en "Rendez-vous confirmé"
                $rendezvous->setStatus('Rendez-vous confirmé');
                $entityManager->flush();
                // Redirection vers la liste des rendez-vous
                return $this->redirectToRoute('app_dashboard_rendezvous');
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