<?php

declare(strict_types=1);

namespace MyFramework\Core\Security\Controller;

use Doctrine\ORM\EntityManagerInterface;
use MyFramework\Core\Entity\PasswordResetToken;
use MyFramework\Core\Security\Form\ResetPasswordFormType;
use MyFramework\Core\Security\Form\ResetPasswordRequestFormType;
use MyFramework\Core\Security\Repository\UserRepository;
use MyFramework\Core\Security\Service\AuthMailer;
use MyFramework\Core\Security\Service\TokenFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class ResetPasswordController extends AbstractController
{
    #[Route(path: '/reset-password', name: 'myframework_auth_reset_password_request', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        TokenFactory $tokenFactory,
        AuthMailer $mailer,
        RateLimiterFactory $authPasswordResetLimiter,
    ): Response {
        // Apply rate limiting based on IP address
        $limiter = $authPasswordResetLimiter->create($request->getClientIp() ?? '0.0.0.0');
        
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check rate limit on actual submission
            if (!$limiter->consume(1)->isAccepted()) {
                $this->addFlash('error', 'Zu viele Anfragen zum Zurücksetzen des Passworts. Bitte versuchen Sie es später erneut.');
                return $this->redirectToRoute('myframework_auth_reset_password_request');
            }

            $email = (string) $form->get('email')->getData();
            $user = $users->findOneBy(['email' => mb_strtolower($email)]);

            // Always behave the same (avoid user enumeration)
            if ($user !== null) {
                $pair = $tokenFactory->createTokenPair();
                $token = (new PasswordResetToken())
                    ->setUser($user)
                    ->setTokenHash($pair['hash'])
                    ->setExpiresAt(new \DateTimeImmutable('+2 hours'));

                $em->persist($token);
                $em->flush();

                $mailer->sendPasswordResetEmail($user, $pair['plain']);
            }

            return $this->render('@MyFrameworkCore/auth/reset_request_sent.html.twig');
        }

        return $this->render('@MyFrameworkCore/auth/reset_request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    #[Route(path: '/reset-password/{token}', name: 'myframework_auth_reset_password', methods: ['GET', 'POST'])]
    public function reset(
        string $token,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $hash = hash('sha256', $token);
        $repo = $em->getRepository(PasswordResetToken::class);

        /** @var PasswordResetToken|null $resetToken */
        $resetToken = $repo->findOneBy(['tokenHash' => $hash]);

        if ($resetToken === null || $resetToken->isConsumed() || $resetToken->isExpired()) {
            return $this->render('@MyFrameworkCore/auth/reset_failed.html.twig');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $resetToken->getUser();
            $hashed = $passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData());
            $user->setPassword($hashed);

            $resetToken->consume();
            $em->flush();

            return $this->redirectToRoute('myframework_auth_login');
        }

        return $this->render('@MyFrameworkCore/auth/reset.html.twig', [
            'resetForm' => $form,
        ]);
    }
}
