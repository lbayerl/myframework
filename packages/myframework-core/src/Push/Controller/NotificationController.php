<?php

declare(strict_types=1);

namespace MyFramework\Core\Push\Controller;

use MyFramework\Core\Push\Service\PushService;
use MyFramework\Core\Entity\PushSubscription;
use MyFramework\Core\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications', name: 'myframework_notifications_')]
#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly PushService $pushService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@MyFrameworkCore/notifications/index.html.twig');
    }

    #[Route('/subscribe', name: 'subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!isset($data['endpoint'], $data['keys']['auth'], $data['keys']['p256dh'])) {
            return $this->json(['error' => 'Invalid subscription data'], Response::HTTP_BAD_REQUEST);
        }

        $deviceLabel = null;
        if (is_string($data['deviceLabel'] ?? null)) {
            $deviceLabel = mb_substr(trim($data['deviceLabel']), 0, 255);
            if ($deviceLabel === '') {
                $deviceLabel = null;
            }
        }

        try {
            $this->pushService->subscribe(
                $user,
                $data['endpoint'],
                $data['keys']['auth'],
                $data['keys']['p256dh'],
                $deviceLabel,
            );

            $this->logger->info('User subscribed to push notifications', [
                'user_id' => $user->getId(),
                'endpoint' => substr($data['endpoint'], 0, 50) . '...',
            ]);

            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to subscribe to push notifications', [
                'user_id' => $user->getId(),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);
            return $this->json(['error' => 'Failed to subscribe to push notifications'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/unsubscribe', name: 'unsubscribe', methods: ['POST'])]
    public function unsubscribe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!isset($data['endpoint'])) {
            return $this->json(['error' => 'Missing endpoint'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->pushService->unsubscribeByUser($user, $data['endpoint']);

            $this->logger->info('User unsubscribed from push notifications', [
                'user_id' => $user->getId(),
                'endpoint' => substr($data['endpoint'], 0, 50) . '...',
            ]);

            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to unsubscribe from push notifications', [
                'user_id' => $user->getId(),
                'endpoint' => substr($data['endpoint'], 0, 50) . '...',
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);
            return $this->json(['error' => 'Failed to unsubscribe from push notifications'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/subscriptions', name: 'subscriptions', methods: ['GET'])]
    public function subscriptions(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $subscriptions = $this->pushService->getSubscriptionsByUser($user);

        $items = array_map(static function (PushSubscription $subscription): array {
            $endpoint = $subscription->getEndpoint();
            $endpointPreview = strlen($endpoint) > 80
                ? substr($endpoint, 0, 40) . '…' . substr($endpoint, -20)
                : $endpoint;

            return [
                'id' => $subscription->getId(),
                'endpoint' => $endpoint,
                'endpointPreview' => $endpointPreview,
                'deviceLabel' => $subscription->getDeviceLabel(),
                'createdAt' => $subscription->getCreatedAt()->format(DATE_ATOM),
            ];
        }, $subscriptions);

        return $this->json([
            'success' => true,
            'subscriptions' => $items,
        ]);
    }

    #[Route('/subscriptions/{id}', name: 'subscriptions_delete', methods: ['DELETE'])]
    public function deleteSubscription(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $deleted = $this->pushService->unsubscribeByUserAndId($user, $id);
            if (!$deleted) {
                return $this->json(['error' => 'Subscription not found'], Response::HTTP_NOT_FOUND);
            }

            $this->logger->info('User deleted push subscription', [
                'user_id' => $user->getId(),
                'subscription_id' => $id,
            ]);

            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete push subscription', [
                'user_id' => $user->getId(),
                'subscription_id' => $id,
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);

            return $this->json(['error' => 'Failed to delete subscription'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/test', name: 'test', methods: ['POST'])]
    public function test(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $reports = $this->pushService->sendToUser(
                $user,
                'Test Notification',
                'This is a test push notification from MyFramework!',
                $this->generateUrl('myframework_notifications_index')
            );

            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($reports as $report) {
                if ($report->isSuccess()) {
                    $successCount++;
                } else {
                    $errorCount++;
                    $errors[] = [
                        'endpoint' => substr($report->getEndpoint(), 0, 50) . '...',
                        'reason' => $report->getReason(),
                    ];
                }
            }

            $this->logger->info('Test notification sent', [
                'user_id' => $user->getId(),
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'total' => count($reports),
                'errors' => $errors,
            ]);

            return $this->json([
                'success' => true,
                'sent' => $successCount,
                'failed' => $errorCount,
                'total' => count($reports),
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send test notification', [
                'user_id' => $user->getId(),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);
            return $this->json(['error' => 'Failed to send test notification'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
