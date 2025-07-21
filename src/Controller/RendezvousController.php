<?php

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
        $rendezvou = new Rendezvous();
        $rendezvou->setStatus("Validé");

        $form = $this->createForm(RendezvousType::class, $rendezvou);
        $form->handleRequest($request);


        if ($form->isSubmitted() && $form->isValid()) {

            $entityManager->persist($rendezvou);
            $entityManager->flush();

            // Récupérer l'adresse e-mail de l'utilisateur à partir du rendez-vous
            $userEmail = $rendezvou->getUser()->getEmail();

            // Envoyer l'e-mail après la création du rendez-vous
            $email = (new Email())

                ->to($userEmail)
                ->subject('Votre Rendez-vous !')
                ->html($this->renderView(
                    'emails/rendezvous_created.html.twig',
                    ['rendezvous' => $rendezvou]
                ));

            $mailer->send($email);

            return $this->redirectToRoute('app_rendezvous_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('rendezvous/new.html.twig', [
            'rendezvou' => $rendezvou,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_rendezvous_show', methods: ['GET'])]
    public function show(Rendezvous $rendezvou): Response
    {
        return $this->render('rendezvous/show.html.twig', [
            'rendezvou' => $rendezvou,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_rendezvous_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Rendezvous $rendezvou, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        // Création d'un formulaire personnalisé avec seulement les champs 'day' et 'creneau'
        $form = $this->createForm(RendezvousModifyType::class, $rendezvou);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérification de l'existence d'un rendez-vous pris ou confirmé
            if ($this->isRendezvousExist($entityManager, $rendezvou)) {
                $this->addFlash('error', 'Un rendez-vous est déjà pris ou confirmé pour cette date et ce créneau.');
                return $this->redirectToRoute('app_rendezvous_edit', ['id' => $rendezvou->getId()]);
            }

            // Persistance des changements en base de données
            $entityManager->flush();

            // Récupérer l'adresse e-mail de l'utilisateur à partir du rendez-vous
            $userEmail = $rendezvou->getUser()->getEmail();

            // Envoyer l'e-mail après la modification du rendez-vous
            $email = (new Email())
                ->from('beellenailscare@beellenails.com')
                ->to($userEmail)
                ->subject('Votre Rendez-vous a été modifié')
                ->html($this->renderView(
                    'emails/rendezvous_updated.html.twig',
                    ['rendezvou' => $rendezvou]
                ));
            $mailer->send($email);

            return $this->redirectToRoute('app_users', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('rendezvous/edit.html.twig', [
            'rendezvou' => $rendezvou,
            'form' => $form->createView(),
        ]);
    }

    private function isRendezvousExist(EntityManagerInterface $entityManager, Rendezvous $rendezvou): bool
    {
        $existingRendezvous = $entityManager->getRepository(Rendezvous::class)->findOneBy([
            'day' => $rendezvou->getDay(),
            'creneau' => $rendezvou->getCreneau(),
            'status' => ['Rendez-vous pris', 'Rendez-vous confirmé']
        ]);

        return $existingRendezvous !== null;
    }

    #[Route('/{id}/cancel', name: 'app_rendezvous_cancel', methods: ['GET', 'POST'])]
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

        // Email admin
        $emailAdmin = (new Email())
            ->from('beellenailscare@beellenails.com')
            ->to('jy.ahouanvoedo@gmail.com')
            ->subject('Un rendez-vous a été annulé')
            ->html($this->renderView(
                'emails/rendezvous_canceled_admin.html.twig',
                ['rendezvous' => $rendezvou]
            ));
        $mailer->send($emailAdmin);
        return $this->redirectToRoute('app_users', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_rendezvous_delete', methods: ['POST'])]
    public function delete(Request $request, Rendezvous $rendezvou, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $rendezvou->getId(), $request->request->get('_token'))) {
            $entityManager->remove($rendezvou);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_rendezvous_index', [], Response::HTTP_SEE_OTHER);
    }
}
