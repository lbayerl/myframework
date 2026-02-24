<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalController extends AbstractController
{
    public function __construct(
        private readonly string $fromEmail,
        private readonly string $appCreator,
    ) {
    }

    #[Route('/legal/privacy', name: 'app_legal_privacy')]
    public function privacy(): Response
    {
        return $this->render('legal/privacy.html.twig', [
            'from_email' => $this->fromEmail,
            'app_creator' => $this->appCreator,
        ]);
    }
}
