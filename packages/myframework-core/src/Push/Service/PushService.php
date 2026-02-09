<?php

declare(strict_types=1);

namespace MyFramework\Core\Push\Service;

use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use MyFramework\Core\Entity\PushSubscription;
use MyFramework\Core\Push\Repository\PushSubscriptionRepository;
use MyFramework\Core\Push\VapidConfig;
use MyFramework\Core\Entity\User;
use Psr\Log\LoggerInterface;

final class PushService
{
    public function __construct(
        private readonly VapidConfig $vapidConfig,
        private readonly PushSubscriptionRepository $repository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function subscribe(
        User $user,
        string $endpoint,
        string $authToken,
        string $p256dhKey,
    ): PushSubscription {
        // Prüfen ob bereits vorhanden
        $existing = $this->repository->findByEndpoint($endpoint);
        if ($existing !== null) {
            return $existing;
        }

        $subscription = new PushSubscription($user, $endpoint, $authToken, $p256dhKey);
        $this->repository->save($subscription);

        return $subscription;
    }

    public function unsubscribe(string $endpoint): void
    {
        $subscription = $this->repository->findByEndpoint($endpoint);
        if ($subscription !== null) {
            $this->repository->remove($subscription);
        }
    }

    /**
     * Sendet eine Push-Notification an einen User
     *
     * @return array<int, MessageSentReport>
     */
    public function sendToUser(User $user, string $title, string $body, ?string $url = null): array
    {
        $subscriptions = $this->repository->findByUser($user);

        return $this->sendToSubscriptions($subscriptions, $title, $body, $url);
    }

    /**
     * Sendet eine Push-Notification an alle registrierten Devices
     *
     * @return array<int, MessageSentReport>
     */
    public function sendToAll(string $title, string $body, ?string $url = null): array
    {
        $subscriptions = $this->repository->findAll();

        return $this->sendToSubscriptions($subscriptions, $title, $body, $url);
    }

    /**
     * @param PushSubscription[] $subscriptions
     * @return array<int, MessageSentReport>
     */
    private function sendToSubscriptions(array $subscriptions, string $title, string $body, ?string $url = null): array
    {
        if (count($subscriptions) === 0) {
            return [];
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $this->vapidConfig->subject,
                'publicKey' => $this->vapidConfig->publicKey,
                'privateKey' => $this->vapidConfig->privateKey,
            ],
        ]);

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ], JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $subscription) {
            $webPushSubscription = Subscription::create([
                'endpoint' => $subscription->getEndpoint(),
                'keys' => [
                    'auth' => $subscription->getAuthToken(),
                    'p256dh' => $subscription->getP256dhKey(),
                ],
            ]);

            $webPush->queueNotification($webPushSubscription, $payload);
        }

        $reports = [];
        foreach ($webPush->flush() as $report) {
            $reports[] = $report;

            // Bei 404/410 (Subscription expired/ungültig) automatisch löschen
            if ($report->isSubscriptionExpired()) {
                $endpoint = $report->getEndpoint();
                $this->logger->info('Push subscription expired, removing', [
                    'endpoint' => $endpoint,
                ]);
                $this->unsubscribe($endpoint);
            } elseif (!$report->isSuccess()) {
                // Log failed notifications with detailed error information
                $this->logger->error('Push notification failed', [
                    'endpoint' => $report->getEndpoint(),
                    'reason' => $report->getReason(),
                    'expired' => $report->isSubscriptionExpired(),
                ]);
            } else {
                $this->logger->debug('Push notification sent successfully', [
                    'endpoint' => $report->getEndpoint(),
                ]);
            }
        }

        return $reports;
    }
}
