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
        ?string $deviceLabel = null,
    ): PushSubscription {
        $existing = $this->repository->findByEndpoint($endpoint);
        if ($existing !== null) {
            $hasChangedUser = $existing->getUser()->getId() !== $user->getId();
            $hasChangedKeys = $existing->getAuthToken() !== $authToken || $existing->getP256dhKey() !== $p256dhKey;
            $hasChangedLabel = $existing->getDeviceLabel() !== $deviceLabel;

            if ($hasChangedUser || $hasChangedKeys || $hasChangedLabel) {
                $existing->updateUser($user);
                $existing->updateKeys($authToken, $p256dhKey);
                $existing->updateDeviceLabel($deviceLabel);
                $this->repository->save($existing);

                $this->logger->info('Push subscription updated for existing endpoint', [
                    'endpoint' => $endpoint,
                    'user_changed' => $hasChangedUser,
                    'keys_changed' => $hasChangedKeys,
                    'label_changed' => $hasChangedLabel,
                ]);
            }

            return $existing;
        }

        $subscription = new PushSubscription($user, $endpoint, $authToken, $p256dhKey, $deviceLabel);
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
     * Unsubscribe only if the subscription belongs to the given user.
     */
    public function unsubscribeByUser(User $user, string $endpoint): void
    {
        $subscription = $this->repository->findByEndpoint($endpoint);
        if ($subscription !== null && $subscription->getUser()->getId() === $user->getId()) {
            $this->repository->remove($subscription);
        }
    }

    /**
     * @return PushSubscription[]
     */
    public function getSubscriptionsByUser(User $user): array
    {
        return $this->repository->findByUser($user);
    }

    public function unsubscribeByUserAndId(User $user, int $subscriptionId): bool
    {
        $subscription = $this->repository->findByIdAndUser($subscriptionId, $user);
        if ($subscription === null) {
            return false;
        }

        $this->repository->remove($subscription);

        return true;
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

            if ($this->shouldRemoveSubscription($report)) {
                $endpoint = $report->getEndpoint();
                $this->logger->info('Push subscription invalid, removing', [
                    'endpoint' => $endpoint,
                    'expired' => $report->isSubscriptionExpired(),
                    'reason' => $report->getReason(),
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

    private function shouldRemoveSubscription(MessageSentReport $report): bool
    {
        if ($report->isSubscriptionExpired() || $this->hasVapidMismatch($report)) {
            return true;
        }

        if ($report->isSuccess()) {
            return false;
        }

        $reason = strtolower($report->getReason() ?? '');

        if ($reason === '') {
            return false;
        }

        return str_contains($reason, '410')
            || str_contains($reason, '404')
            || str_contains($reason, 'gone')
            || str_contains($reason, 'not found')
            || str_contains($reason, 'invalid subscription')
            || str_contains($reason, 'subscription is no longer valid')
            || str_contains($reason, 'subscription does not exist')
            || str_contains($reason, 'endpoint is no longer valid');
    }

    private function hasVapidMismatch(MessageSentReport $report): bool
    {
        if ($report->isSuccess()) {
            return false;
        }

        $reason = strtolower($report->getReason() ?? '');

        if ($reason === '') {
            return false;
        }

        return str_contains($reason, 'vapid credentials in the authorization header do not correspond')
            || str_contains($reason, 'vapid public key mismatch')
            || (str_contains($reason, 'unauthorized') && str_contains($reason, 'vapid'));
    }
}
