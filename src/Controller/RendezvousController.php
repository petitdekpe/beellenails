<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Controller;

use App\Entity\Rendezvous;
use App\Form\RendezvousType;
use App\Service\PromoCodeService;
use Symfony\Component\Mime\Email;
use App\Form\RendezvousModifyType;
use App\Repository\RendezvousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/rendezvous')]
class RendezvousController extends AbstractController
{
    #[Route('/', name: 'app_rendezvous_index', methods: ['GET'])]
    public function index(RendezvousRepository $rendezvousRepository): Response
    {
        return $this->render('rendezvous/index.html.twig', [
            'rendezvouses' => $rendezvousRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_rendezvous_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer, UserInterface $user): Response
    {
        $rendezvous = new Rendezvous();
        $rendezvous->setStatus("Validé");

        $form = $this->createForm(RendezvousType::class, $rendezvous);
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
                return $this->render('rendezvous/new.html.twig', [
                    'rendezvous' => $rendezvous,
                    'form' => $form,
                ]);
            }

            // Vérifier si le créneau est déjà en congé (double vérification)
            $existingConge = $entityManager->getRepository(Rendezvous::class)->findOneBy([
                'day' => $rendezvous->getDay(),
                'creneau' => $rendezvous->getCreneau(),
                'status' => 'Congé'
            ]);

            if ($existingConge) {
                $this->addFlash('error', 'Ce créneau est indisponible (en congé).');
                return $this->render('rendezvous/new.html.twig', [
                    'rendezvous' => $rendezvous,
                    'form' => $form,
                ]);
            }

            $entityManager->persist($rendezvous);
            $entityManager->flush();

            // Récupérer l'adresse e-mail de l'utilisateur à partir du rendez-vous
            $userEmail = $rendezvous->getUser()->getEmail();

            // Envoyer l'e-mail après la création du rendez-vous
            $email = (new Email())

                ->to($userEmail)
                ->subject('Votre Rendez-vous !')
                ->html($this->renderView(
                    'emails/rendezvous_created.html.twig',
                    ['rendezvous' => $rendezvous]
                ));

            $mailer->send($email);

            return $this->redirectToRoute('app_rendezvous_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('rendezvous/new.html.twig', [
            'rendezvous' => $rendezvous,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_rendezvous_show', methods: ['GET'])]
    public function show(Rendezvous $rendezvous): Response
    {
        return $this->render('rendezvous/show.html.twig', [
            'rendezvous' => $rendezvous,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_rendezvous_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Rendezvous $rendezvous, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        // Sauvegarder les anciennes informations avant la modification
        $originalDay = $rendezvous->getDay();
        $originalCreneau = $rendezvous->getCreneau();
        
        // Création d'un formulaire personnalisé avec seulement les champs 'day' et 'creneau'
        $form = $this->createForm(RendezvousModifyType::class, $rendezvous);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérification que le créneau est bien sélectionné
            if (!$rendezvous->getCreneau()) {
                $this->addFlash('error', 'Veuillez sélectionner un créneau horaire pour le rendez-vous.');
                return $this->redirectToRoute('app_rendezvous_edit', ['id' => $rendezvous->getId()]);
            }

            // Vérification de l'existence d'un rendez-vous pris ou confirmé
            if ($this->isRendezvousExist($entityManager, $rendezvous)) {
                $this->addFlash('error', 'Un rendez-vous est déjà pris ou confirmé pour cette date et ce créneau.');
                return $this->redirectToRoute('app_rendezvous_edit', ['id' => $rendezvous->getId()]);
            }

            // Sauvegarder l'historique des modifications
            $rendezvous->setPreviousDay($originalDay);
            $rendezvous->setPreviousCreneau($originalCreneau);

            // Persistance des changements en base de données
            $entityManager->flush();

            // Récupérer l'adresse e-mail de l'utilisateur à partir du rendez-vous
            $userEmail = $rendezvous->getUser()->getEmail();

            // Envoyer l'e-mail après la modification du rendez-vous
            $email = (new Email())
                ->from('BeElle Nails Care <reservation@beellegroup.com>')
                ->to($userEmail)
                ->replyTo('reservation@beellegroup.com')
                ->subject('Votre Rendez-vous a été modifié')
                ->html($this->renderView(
                    'emails/rendezvous_updated.html.twig',
                    ['rendezvous' => $rendezvous]
                ));
            $email->getHeaders()
                ->addTextHeader('X-Mailer', 'BeElle Nails Booking System')
                ->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');
            $mailer->send($email);

            // Envoyer l'e-mail à l'admin
            $adminEmail = (new Email())
                ->from('BeElle Nails Care <reservation@beellegroup.com>')
                ->to('murielahodode@gmail.com')
                ->replyTo('reservation@beellegroup.com')
                ->subject('Rendez-vous modifié par le client')
                ->html($this->renderView(
                    'emails/rendezvous_updated_admin.html.twig',
                    ['rendezvous' => $rendezvous]
                ));
            $adminEmail->getHeaders()
                ->addTextHeader('X-Mailer', 'BeElle Nails Booking System')
                ->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');
            $mailer->send($adminEmail);

            return $this->redirectToRoute('app_users', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('rendezvous/edit.html.twig', [
            'rendezvous' => $rendezvous,
            'form' => $form->createView(),
        ]);
    }

    private function isRendezvousExist(EntityManagerInterface $entityManager, Rendezvous $rendezvous): bool
    {
        $existingRendezvous = $entityManager->getRepository(Rendezvous::class)->findOneBy([
            'day' => $rendezvous->getDay(),
            'creneau' => $rendezvous->getCreneau(),
            'status' => ['Rendez-vous pris', 'Rendez-vous confirmé']
        ]);

        return $existingRendezvous !== null;
    }

    #[Route('/{id}/cancel', name: 'app_rendezvous_cancel', methods: ['GET', 'POST'])]
    public function cancel(Request $request, Rendezvous $rendezvous, EntityManagerInterface $entityManager, MailerInterface $mailer, PromoCodeService $promoCodeService, LoggerInterface $logger): Response
    {
        $rendezvous->setStatus("Annulé");
        
        // Révoquer le code promo si il y en a un
        if ($rendezvous->getPromoCode()) {
            $result = $promoCodeService->revokePromoCodeUsage($rendezvous, 'Rendez-vous annulé par le client');
            $logger->info("[Client Cancellation] Code promo révoqué suite à l'annulation", [
                'rendezvous_id' => $rendezvous->getId(),
                'reason' => 'Rendez-vous annulé par le client'
            ]);
        }

        $entityManager->persist($rendezvous);
        $entityManager->flush();

        // Récupérer l'adresse e-mail de l'utilisateur à partir du rendez-vous
        $userEmail = $rendezvous->getUser()->getEmail();

        // Envoyer l'e-mail après la création du rendez-vous
        $email = (new Email())
            ->from('BeElle Nails Care <reservation@beellegroup.com>')
            ->to($userEmail)
            ->replyTo('reservation@beellegroup.com')
            ->subject('Rendez-vous Annulé !')
            ->html($this->renderView(
                'emails/rendezvous_canceled.html.twig',
                ['rendezvous' => $rendezvous]
            ));
        $email->getHeaders()
            ->addTextHeader('X-Mailer', 'BeElle Nails Booking System')
            ->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');

        $mailer->send($email);

        // Email admin
        $emailAdmin = (new Email())
            ->from('BeElle Nails Care <reservation@beellegroup.com>')
            ->to('murielahodode@gmail.com')
            ->replyTo('reservation@beellegroup.com')
            ->subject('Un rendez-vous a été annulé')
            ->html($this->renderView(
                'emails/rendezvous_canceled_admin.html.twig',
                ['rendezvous' => $rendezvous]
            ));
        $emailAdmin->getHeaders()
            ->addTextHeader('X-Mailer', 'BeElle Nails Booking System')
            ->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');
        $mailer->send($emailAdmin);
        return $this->redirectToRoute('app_users', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_rendezvous_delete', methods: ['POST'])]
    public function delete(Request $request, Rendezvous $rendezvous, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $rendezvous->getId(), $request->request->get('_token'))) {
            $entityManager->remove($rendezvous);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_rendezvous_index', [], Response::HTTP_SEE_OTHER);
    }
}
