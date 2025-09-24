<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\User;
use App\Form\BulkEmailType;
use App\Repository\UserRepository;
use Symfony\Component\Mime\Email;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class EmailManagementController extends AbstractController
{
    #[Route('/dashboard/emails', name: 'app_dashboard_emails')]
    public function index(Request $request, UserRepository $userRepository, MailerInterface $mailer): Response
    {
        $form = $this->createForm(BulkEmailType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $subject = $data['subject'];
            $message = $data['message'];
            $recipients = $data['recipients'];
            $sendToAll = $data['sendToAll'];
            
            $emailsSent = 0;
            $errors = [];

            try {
                if ($sendToAll) {
                    // Envoyer à tous les clients (excluant les admins)
                    $users = $userRepository->createQueryBuilder('u')
                        ->where('u.roles NOT LIKE :admin_role')
                        ->setParameter('admin_role', '%ROLE_ADMIN%')
                        ->getQuery()
                        ->getResult();
                } else {
                    // Envoyer aux utilisateurs sélectionnés
                    $users = $recipients;
                }

                foreach ($users as $user) {
                    try {
                        // Personnaliser le message avec les données du client
                        $personalizedMessage = $this->personalizeMessage($message, $user);
                        $personalizedSubject = $this->personalizeMessage($subject, $user);
                        
                        $email = (new Email())
                            ->from('beellenailscare@beellenails.com')
                            ->replyTo('murielahodode@gmail.com')
                            ->to($user->getEmail())
                            ->subject($personalizedSubject)
                            ->html($this->renderView('emails/bulk_email.html.twig', [
                                'user' => $user,
                                'message' => $personalizedMessage,
                                'subject' => $personalizedSubject
                            ]))
                            ->text(strip_tags($personalizedMessage)); // Version texte
                            
                        // Ajouter les headers anti-spam
                        $email->getHeaders()
                            ->addTextHeader('X-Mailer', 'Beelle Nails Care System')
                            ->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN')
                            ->addTextHeader('List-Unsubscribe', '<mailto:murielahodode@gmail.com?subject=Unsubscribe>')
                            ->addTextHeader('Precedence', 'bulk');
                        
                        $mailer->send($email);
                        $emailsSent++;
                    } catch (\Exception $e) {
                        $errors[] = "Erreur pour {$user->getEmail()}: " . $e->getMessage();
                    }
                }

                if ($emailsSent > 0) {
                    $this->addFlash('success', "✅ {$emailsSent} email(s) envoyé(s) avec succès.");
                }
                
                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        $this->addFlash('error', $error);
                    }
                }

            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'envoi des emails: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_dashboard_emails');
        }

        // Récupérer tous les clients pour la sélection (excluant les admins)
        $clients = $userRepository->createQueryBuilder('u')
            ->where('u.roles NOT LIKE :admin_role')
            ->setParameter('admin_role', '%ROLE_ADMIN%')
            ->orderBy('u.Prenom', 'ASC')
            ->addOrderBy('u.Nom', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('dashboard/emails/index.html.twig', [
            'form' => $form->createView(),
            'clients' => $clients,
            'total_clients' => count($clients)
        ]);
    }
    
    /**
     * Personnalise le message en remplaçant les balises par les données du client
     */
    private function personalizeMessage(string $message, User $user): string
    {
        $replacements = [
            '{{prenom}}' => $user->getPrenom() ?? '',
            '{{nom}}' => $user->getNom() ?? '',
            '{{nom_complet}}' => $user->getFullName() ?? '',
            '{{email}}' => $user->getEmail() ?? '',
            '{{telephone}}' => $user->getPhone() ?? '',
            '{{date}}' => (new \DateTime())->format('d/m/Y'),
            '{{heure}}' => (new \DateTime())->format('H:i'),
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }
}