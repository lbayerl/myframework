<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\GuestConversionService;
use MyFramework\Core\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * Converts guest entries to user references when a user logs in.
 * This handles the case where a guest was added first and later registered.
 */
final class GuestConversionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly GuestConversionService $conversionService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InteractiveLoginEvent::class => 'onInteractiveLogin',
        ];
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user instanceof User) {
            return;
        }

        $this->conversionService->convertGuestsForUser($user);
    }
}
