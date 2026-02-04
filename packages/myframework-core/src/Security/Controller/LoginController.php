<?php

declare(strict_types=1);

namespace MyFramework\Core\Security\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractController
{
    #[Route(path: '/login', name: 'myframework_auth_login')]
    public function login(
        AuthenticationUtils $authenticationUtils,
        RateLimiterFactory $authLoginLimiter,
    ): Response {
        // Apply rate limiting based on IP address
        $limiter = $authLoginLimiter->create($this->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Zu viele Anmeldeversuche. Bitte versuchen Sie es später erneut.');
        }

        return $this->render('@MyFrameworkCore/auth/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route(path: '/logout', name: 'myframework_auth_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    private function getClientIp(): string
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        return $request?->getClientIp() ?? '0.0.0.0';
    }
}
