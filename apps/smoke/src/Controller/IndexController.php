<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class IndexController extends AbstractController
{
    #[Route('/', name: 'app_index', methods: ['GET'])]
    public function index(): Response
    {
        // Wenn User eingeloggt ist, direkt zu Home weiterleiten
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Sonst Landing-Page mit Login/Register anzeigen
        return $this->render('index/index.html.twig');
    }
}
