<?php

declare(strict_types=1);

namespace MyFramework\Core\Security\Service;

use MyFramework\Core\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final class AuthMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RouterInterface $router,
        private readonly string $fromEmail,
    ) {
    }

    public function sendVerifyEmail(User $user, string $plainToken): void
    {
        $url = $this->router->generate('myframework_auth_verify_email', ['token' => $plainToken], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('Bitte bestätige deine E-Mail')
            ->text("Bitte bestätige deine E-Mail über folgenden Link: $url");

        $this->mailer->send($email);
    }

    public function sendPasswordResetEmail(User $user, string $plainToken): void
    {
        $url = $this->router->generate('myframework_auth_reset_password', ['token' => $plainToken], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('Passwort zurücksetzen')
            ->text("Du kannst dein Passwort hier zurücksetzen: $url");

        $this->mailer->send($email);
    }
}
