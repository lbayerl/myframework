<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ConcertAttendee;
use App\Entity\Guest;
use App\Entity\Ticket;
use App\Repository\ConcertAttendeeRepository;
use App\Repository\GuestRepository;
use Doctrine\ORM\EntityManagerInterface;
use MyFramework\Core\Entity\User;

final class GuestConversionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GuestRepository $guestRepo,
        private readonly ConcertAttendeeRepository $attendeeRepo,
    ) {
    }

    /**
     * Convert all guest entries matching a given email to a real user.
     * Updates ticket and concert attendee references from guest to user.
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

            // Update concert attendee records: guest → user
            $guestAttendees = $this->attendeeRepo->findBy(['guest' => $guest]);
            foreach ($guestAttendees as $attendee) {
                // Check if user already has an attendee record for this concert
                $existingUserAttendee = $this->attendeeRepo->findOneByUserAndConcert($user, $attendee->getConcert());

                if ($existingUserAttendee !== null) {
                    // User already has a record for this concert — remove the guest duplicate
                    $this->em->remove($attendee);
                } else {
                    // Transfer the guest attendee to the user
                    $attendee->setUser($user);
                    $attendee->setGuest(null);
                }
            }

            // Mark guest as converted
            $guest->setConvertedToUser($user);
            $guest->touch();
            $converted++;
        }

        $this->em->flush();

        return $converted;
    }

    /**
     * Manually link a specific guest to a specific user (admin action).
     * Transfers all tickets and attendee records from guest to user.
     */
    public function convertGuestToUser(Guest $guest, User $user): void
    {
        $ticketRepo = $this->em->getRepository(Ticket::class);

        // Transfer tickets: guest owner → user
        $ownerTickets = $ticketRepo->findBy(['guestOwner' => $guest]);
        foreach ($ownerTickets as $ticket) {
            $ticket->setOwner($user);
            $ticket->setGuestOwner(null);
        }

        // Transfer tickets: guest purchaser → user
        $purchaserTickets = $ticketRepo->findBy(['guestPurchaser' => $guest]);
        foreach ($purchaserTickets as $ticket) {
            $ticket->setPurchaser($user);
            $ticket->setGuestPurchaser(null);
        }

        // Transfer concert attendee records
        $guestAttendees = $this->attendeeRepo->findBy(['guest' => $guest]);
        foreach ($guestAttendees as $attendee) {
            $existingUserAttendee = $this->attendeeRepo->findOneByUserAndConcert($user, $attendee->getConcert());

            if ($existingUserAttendee !== null) {
                $this->em->remove($attendee);
            } else {
                $attendee->setUser($user);
                $attendee->setGuest(null);
            }
        }

        // Mark guest as converted
        $guest->setConvertedToUser($user);
        $guest->touch();

        $this->em->flush();
    }
}
