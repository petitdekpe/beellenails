<?php

namespace App\Controller;

use App\Form\TermsType;
use App\Entity\Rendezvous;
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
    public function index(Rendezvous $rendezvou, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Créer le formulaire TermsType 
        $form = $this->createForm(TermsType::class);
        $form->handleRequest($request);
        
        $user = $this->getUser(); 
        // Ajouter l'utilisateur actuel au rendez-vous

        if ($form->isSubmitted() && $form->isValid()) {
            $rendezvou->setUser($user);
            $entityManager->persist($rendezvou);
            $entityManager->flush();

            return $this->redirectToRoute('payment_init', ['rendezvou' => $rendezvou->getId()]);
        }

        // Récupérer les suppléments associés à ce rendez-vous
        $supplements = $rendezvou->getSupplement();

        return $this->render('recap/index.html.twig', [
            'form' => $form->createView(),
            'supplements' => $supplements, // Passer la liste des suppléments à la vue Twig
        ]);
    }
}
