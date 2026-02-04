<?php

declare(strict_types=1);

namespace MyFramework\Core\Security\Controller;

use Doctrine\ORM\EntityManagerInterface;
use MyFramework\Core\Entity\EmailVerificationToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class VerifyEmailController extends AbstractController
{
    #[Route(path: '/verify-email', name: 'myframework_auth_verify_email')]
    public function verify(
        Request $request,
        EntityManagerInterface $em,
        RateLimiterFactory $authEmailVerificationLimiter,
    ): Response {
        // Apply rate limiting based on IP address
        $limiter = $authEmailVerificationLimiter->create($request->getClientIp() ?? '0.0.0.0');
        if (!$limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Zu viele Verifizierungsversuche. Bitte versuchen Sie es später erneut.');
            return $this->render('@MyFrameworkCore/auth/verify_email_failed.html.twig');
        }

        $plainToken = (string) $request->query->get('token', '');
        if ($plainToken === '') {
            throw $this->createNotFoundException();
        }

        $hash = hash('sha256', $plainToken);
        $repo = $em->getRepository(EmailVerificationToken::class);

        /** @var EmailVerificationToken|null $token */
        $token = $repo->findOneBy(['tokenHash' => $hash]);

        if ($token === null || $token->isConsumed() || $token->isExpired()) {
            return $this->render('@MyFrameworkCore/auth/verify_email_failed.html.twig');
        }

        $user = $token->getUser();
        $user->setVerified(true);
        $token->consume();

        $em->flush();

        return $this->render('@MyFrameworkCore/auth/verify_email_success.html.twig');
    }
}
