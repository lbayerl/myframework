<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Guest;
use App\Entity\Ticket;
use App\Repository\GuestRepository;
use Doctrine\ORM\EntityManagerInterface;
use MyFramework\Core\Entity\User;

final class GuestConversionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GuestRepository $guestRepo,
    ) {
    }

    /**
     * Convert all guest entries matching a given email to a real user.
     * Updates ticket references from guest to user.
     */
    public function convertGuestsForUser(User $user): int
    {
        $email = $user->getEmail();
        $guests = $this->guestRepo->findAllByEmail($email);

        if (empty($guests)) {
            return 0;
        }

        $ticketRepo = $this->em->getRepository(Ticket::class);
        $converted = 0;

        foreach ($guests as $guest) {
            // Update tickets where this guest is the owner
            $ownerTickets = $ticketRepo->findBy(['guestOwner' => $guest]);
            foreach ($ownerTickets as $ticket) {
                $ticket->setOwner($user);
                $ticket->setGuestOwner(null);
            }

            // Update tickets where this guest is the purchaser
            $purchaserTickets = $ticketRepo->findBy(['guestPurchaser' => $guest]);
            foreach ($purchaserTickets as $ticket) {
                $ticket->setPurchaser($user);
                $ticket->setGuestPurchaser(null);
            }

            // Mark guest as converted
            $guest->setConvertedToUser($user);
            $guest->touch();
            $converted++;
        }

        $this->em->flush();

        return $converted;
    }
}
