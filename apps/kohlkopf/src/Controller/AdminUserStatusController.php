<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use MyFramework\Core\Entity\PushSubscription;
use MyFramework\Core\Entity\User;
use MyFramework\Core\Push\Repository\PushSubscriptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin')]
final class AdminUserStatusController extends AbstractController
{
    #[Route('/users-status', name: 'admin_users_status', methods: ['GET'])]
    public function usersStatus(EntityManagerInterface $entityManager, PushSubscriptionRepository $pushSubscriptionRepository): Response
    {
        $users = $entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->orderBy('u.displayName', 'ASC')
            ->addOrderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();

        $subscriptionsByUserId = [];

        foreach ($pushSubscriptionRepository->findAll() as $subscription) {
            $userId = $subscription->getUser()->getId();
            if ($userId === null) {
                continue;
            }

            if (!array_key_exists($userId, $subscriptionsByUserId)) {
                $subscriptionsByUserId[$userId] = [];
            }

            $subscriptionsByUserId[$userId][] = $this->resolveSubscriptionType($subscription);
        }

        $rows = [];
        foreach ($users as $user) {
            $userId = $user->getId();
            $subscriptionTypes = $userId !== null ? ($subscriptionsByUserId[$userId] ?? []) : [];

            $rows[] = [
                'name' => $user->getDisplayName(),
                'email' => $user->getEmail(),
                'isVerified' => $user->isVerified(),
                'pushSubscriptionCount' => count($subscriptionTypes),
                'pushSubscriptionTypes' => array_values(array_unique($subscriptionTypes)),
                'lastLoginLabel' => '—',
            ];
        }

        return $this->render('admin/users_status.html.twig', [
            'rows' => $rows,
        ]);
    }

    private function resolveSubscriptionType(PushSubscription $subscription): string
    {
        $deviceLabel = $subscription->getDeviceLabel();
        if ($deviceLabel !== null && trim($deviceLabel) !== '') {
            return $deviceLabel;
        }

        $host = parse_url($subscription->getEndpoint(), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return 'Unbekannt';
        }

        if (str_contains($host, 'fcm.googleapis.com')) {
            return 'Chrome/Edge (FCM)';
        }

        if (str_contains($host, 'updates.push.services.mozilla.com')) {
            return 'Firefox';
        }

        if (str_contains($host, 'push.apple.com')) {
            return 'Safari';
        }

        return $host;
    }
}
