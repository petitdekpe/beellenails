<?php

namespace App\Controller;

use App\Form\TermsType;
use App\Entity\Rendezvous;
use App\Repository\RendezvousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class RecapController extends AbstractController
{
    #[Route('/recap/{rendezvou}', name: 'app_recap')]
    #[IsGranted("ROLE_USER")]
    public function index(Rendezvous $rendezvou, Request $request, EntityManagerInterface $entityManager, RendezvousRepository $rendezvousRepository): Response
    {
        // Créer le formulaire TermsType 
        $form = $this->createForm(TermsType::class);
        $form->handleRequest($request);

        $user = $this->getUser();
        $rendezvou->setUser($user);
        $rendezvou->setStatus("Tentative échoué");

        // Vérifier si un rendez-vous avec le même jour et créneau existe déjà
        $existingRendezvous = $rendezvousRepository->findOneBy([
            'day' => $rendezvou->getDay(),
            'creneau' => $rendezvou->getCreneau(),
            'status' => 'Rendez-vous pris'
        ]);

        if ($existingRendezvous) {
            // Si un rendez-vous existe déjà, rediriger vers la page de prise de rendez-vous
            return $this->redirectToRoute('app_calendar');
        }

        $entityManager->persist($rendezvou);
        $entityManager->flush();

        // Ajouter l'utilisateur actuel au rendez-vous

        if ($form->isSubmitted() && $form->isValid()) {

            return $this->redirectToRoute('payment_choice', ['rendezvou' => $rendezvou->getId()]);
        }

        // Récupérer les suppléments associés à ce rendez-vous
        $supplements = $rendezvou->getSupplement();

        return $this->render('recap/index.html.twig', [
            'form' => $form->createView(),
            'supplements' => $supplements, // Passer la liste des suppléments à la vue Twig
        ]);
    }
}
