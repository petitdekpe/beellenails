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
    
    public function sendMail(MailerInterface $mailer)
    {
            $mail = (new Email())
                ->from('thebest@demo.test')
                ->to('jy.ahouanvoedo@gmail.com')
                ->subject('Mon beau sujet')
                ->html('<h1>Tu es bon</h1><br><p>Ceci est mon message pour toi</p>');
    
            $mailer->send($mail);
            
        
    }
}
