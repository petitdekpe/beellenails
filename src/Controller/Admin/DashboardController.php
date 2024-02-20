<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\Creneau;
use App\Entity\Prestation;
use App\Entity\Rendezvous;
use App\Entity\CategoryPrestation;
use App\Controller\Admin\UserCrudController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\Admin\PrestationCrudController;
use App\Controller\Admin\RendezvousCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    #[IsGranted("ROLE_ADMIN")]
    public function index(): Response
    {
        // return parent::index();

        // Option 1. You can make your dashboard redirect to some common page of your backend
        //
    
        $routeBuilder = $this->container->get(AdminUrlGenerator::class);
        $url = $routeBuilder->setController(RendezvousCrudController::class)->generateUrl();
        
        return $this->redirect($url);

        // Option 2. You can make your dashboard redirect to different pages depending on the user
        //
        // if ('jane' === $this->getUser()->getUsername()) {
        //     return $this->redirect('...');
        // }

        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
        // return $this->render('some/path/my-dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('BeElle');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Rendez-vous', 'fas fa-list', Rendezvous::class);
        yield MenuItem::linkToCrud('Prestation', 'fas fa-list', Prestation::class);
        yield MenuItem::linkToCrud('Categorie de Prestation', 'fas fa-list', CategoryPrestation::class);
        yield MenuItem::linkToCrud('Creneau', 'fas fa-list', Creneau::class);
        yield MenuItem::linkToCrud('Utilisateurs', 'fas fa-list', User::class);
    }
}
