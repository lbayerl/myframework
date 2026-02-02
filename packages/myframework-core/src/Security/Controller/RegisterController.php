<?php

declare(strict_types=1);

namespace MyFramework\Core\Security\Controller;

use Doctrine\ORM\EntityManagerInterface;
use MyFramework\Core\Entity\EmailVerificationToken;
use MyFramework\Core\Entity\User;
use MyFramework\Core\Security\Form\RegistrationFormType;
use MyFramework\Core\Security\Service\AuthMailer;
use MyFramework\Core\Security\Service\TokenFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterController extends AbstractController
{
    #[Route(path: '/register', name: 'myframework_auth_register')]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        TokenFactory $tokenFactory,
        AuthMailer $mailer,
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $hashed = $passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData());
            $user->setPassword($hashed);
            $user->setVerified(false);

            $pair = $tokenFactory->createTokenPair();
            $token = (new EmailVerificationToken())
                ->setUser($user)
                ->setTokenHash($pair['hash'])
                ->setExpiresAt(new \DateTimeImmutable('+1 day'));

            $em->persist($user);
            $em->persist($token);
            $em->flush();

            $mailer->sendVerifyEmail($user, $pair['plain']);

            return $this->redirectToRoute('myframework_auth_login');
        }

        return $this->render('@MyFrameworkCore/auth/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
