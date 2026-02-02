<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        // Demo: Toast Notification
        $this->addFlash('success', 'Willkommen zurück!');

        return $this->render('home/index.html.twig');
    }
}
