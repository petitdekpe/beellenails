<?php

namespace App\Controller;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class MailController extends AbstractController
{
    #[Route('/mail', name: 'app_mail')]
    
    //public function index(): Response
    //{
    //    return $this->render('mail/index.html.twig', [
    //        'controller_name' => 'MailController',
    //    ]);
    //}
    
    public function sendMail(MailerInterface $mailer): Response
    {
        try {
            $mail = (new Email())
                ->from('expediteur@demo.test')
                ->to('petitdekpe@gmail.com')
                ->subject('Mon beau sujet')
                ->html('<p>Ceci est mon message en HTML</p>');
    
            $mailer->send($mail);
    
            // Return a confirmation response
            return new Response('Email envoyé avec succès!');
        } catch (TransportExceptionInterface $e) {
            // Log or handle the error
            return new Response('Erreur lors de l\'envoi de l\'email: ' . $e->getMessage());
        }
    }
}
