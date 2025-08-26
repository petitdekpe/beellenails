<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>


namespace App\Controller;

use App\Entity\Rendezvous;
use App\Form\RendezvousType;
use Symfony\Component\Mime\Email;
use App\Form\RendezvousModifyType;
use App\Repository\RendezvousRepository;
use Doctrine\ORM\EntityManagerInterface;
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

            // Persistance des changements en base de données
            $entityManager->flush();

            // Récupérer l'adresse e-mail de l'utilisateur à partir du rendez-vous
            $userEmail = $rendezvous->getUser()->getEmail();

            // Envoyer l'e-mail après la modification du rendez-vous
            $email = (new Email())
                ->from('beellenailscare@beellenails.com')
                ->to($userEmail)
                ->subject('Votre Rendez-vous a été modifié')
                ->html($this->renderView(
                    'emails/rendezvous_updated.html.twig',
                    ['rendezvous' => $rendezvous]
                ));
            $mailer->send($email);

            // Envoyer l'e-mail à l'admin
            $adminEmail = (new Email())
                ->from('beellenailscare@beellenails.com')
                ->to('murielahodode@gmail.com')
                ->subject('Rendez-vous modifié par le client')
                ->html($this->renderView(
                    'emails/rendezvous_updated_admin.html.twig',
                    ['rendezvous' => $rendezvous]
                ));
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
    public function cancel(Request $request, Rendezvous $rendezvous, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $rendezvous->setStatus("Annulé");

        $entityManager->persist($rendezvous);
        $entityManager->flush();

        // Récupérer l'adresse e-mail de l'utilisateur à partir du rendez-vous
        $userEmail = $rendezvous->getUser()->getEmail();

        // Envoyer l'e-mail après la création du rendez-vous
        $email = (new Email())
            ->from('beellenailscare@beellenails.com')
            ->to($userEmail)
            ->subject('Rendez-vous Annulé !')
            ->html($this->renderView(
                'emails/rendezvous_canceled.html.twig',
                ['rendezvous' => $rendezvous]
            ));

        $mailer->send($email);

        // Email admin
        $emailAdmin = (new Email())
            ->from('beellenailscare@beellenails.com')
            ->to('murielahodode@gmail.com')
            ->subject('Un rendez-vous a été annulé')
            ->html($this->renderView(
                'emails/rendezvous_canceled_admin.html.twig',
                ['rendezvous' => $rendezvous]
            ));
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
