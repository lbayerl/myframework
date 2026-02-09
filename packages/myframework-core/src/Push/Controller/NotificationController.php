<?php

declare(strict_types=1);

namespace MyFramework\Core\Push\Controller;

use MyFramework\Core\Push\Service\PushService;
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

        try {
            $this->pushService->subscribe(
                $user,
                $data['endpoint'],
                $data['keys']['auth'],
                $data['keys']['p256dh']
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
        $data = json_decode($request->getContent(), true);

        if (!isset($data['endpoint'])) {
            return $this->json(['error' => 'Missing endpoint'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->pushService->unsubscribe($data['endpoint']);

            $this->logger->info('User unsubscribed from push notifications', [
                'endpoint' => substr($data['endpoint'], 0, 50) . '...',
            ]);

            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to unsubscribe from push notifications', [
                'endpoint' => isset($data['endpoint']) ? substr($data['endpoint'], 0, 50) . '...' : 'unknown',
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);
            return $this->json(['error' => 'Failed to unsubscribe from push notifications'], Response::HTTP_INTERNAL_SERVER_ERROR);
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
