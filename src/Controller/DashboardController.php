<?php

namespace App\Controller;

use App\Form\AdminAddRdvType;
use Symfony\Component\Mime\Email;
use App\Form\RendezvousModifyType;
use App\Repository\UserRepository;
use App\Repository\PaymentRepository;
use App\Repository\PrestationRepository;
use App\Repository\RendezvousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Rendezvous; // Import de l'entité Rendezvous
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class DashboardController extends AbstractController
{
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

            $color = 'blue'; // Couleur par défaut

            // Si le statut du rendez-vous est "Rendez-vous pris", définissez la couleur sur rouge
            if ($rendezvous->getStatus() === 'Rendez-vous pris') {
            $color = 'red';
            }

            $event = [
                'id' => $rendezvous->getId(),
                'title' => $rendezvous->getUser()->getNom() . ' ' . $rendezvous->getUser()->getPrenom(),
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


    #[Route('/dashboard/prestation', name: 'app_dashboard_prestation', methods: ['GET'])]
    public function prestation(PrestationRepository $prestationRepository): Response
    {
        return $this->render('dashboard/prestation.html.twig', [
            'prestations' => $prestationRepository->findAll(),
        ]);
    }

    #[Route('/dashboard/rendezvous', name: 'app_dashboard_rendezvous', methods: ['GET'])]
    public function rendezvous(RendezvousRepository $rendezvousRepository): Response
    {
        return $this->render('dashboard/rendezvous.html.twig', [
            'rendezvouses' => $rendezvousRepository->findAll(),
        ]);
    }

    #[Route('/dashboard/clients', name: 'app_dashboard_user', methods: ['GET'])]
    public function user(UserRepository $userRepository): Response
    {
        return $this->render('dashboard/user.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/dashboard/transactions', name: 'app_dashboard_transactions', methods: ['GET'])]
    public function payment(PaymentRepository $paymentRepository): Response
    {
        return $this->render('dashboard/payment.html.twig', [
            'payments' => $paymentRepository->findAll(),
        ]);
    }

    #[Route('/dashboard/rendezvous/add', name: 'app_admin_rdv')]
    public function new(Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $rendezvous = new Rendezvous();
        $form = $this->createForm(AdminAddRdvType::class, $rendezvous);
        $form->handleRequest($request);
        

        if ($form->isSubmitted() && $form->isValid()) {
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
    
    


}
