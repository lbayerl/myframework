<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ConcertRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function index(ConcertRepository $concertRepository): Response
    {
        $concerts = $concertRepository->findUpcoming();

        return $this->render('home/index.html.twig', [
            'concerts' => $concerts,
        ]);
    }
}
