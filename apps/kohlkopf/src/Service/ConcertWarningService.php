<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Concert;
use App\Enum\AttendeeStatus;
use App\Enum\ConcertStatus;
use App\Repository\ConcertAttendeeRepository;
use App\Repository\ConcertRepository;
use App\Repository\TicketRepository;
use MyFramework\Core\Entity\User;

/**
 * Ermittelt Warnungen/Handlungsbedarf für die "Meine Konzerte"-Seite.
 *
 * Implementierte Szenarien:
 * - A1: Konzert < 7 Tage, keine Teilnahme-Aussage
 * - B1: ATTENDING/INTERESTED, aber kein Ticket
 * - C1: Ticket ohne Owner (Warnung an Ticket-Ersteller)
 * - E1: Ticket unbezahlt, Preis > 0, Owner ≠ Purchaser
 * - E2: Vergangenes Konzert mit offenen Ticket-Schulden
 */
final class ConcertWarningService
{
    private const int SOON_DAYS = 7;

    public function __construct(
        private readonly ConcertRepository $concertRepo,
        private readonly ConcertAttendeeRepository $attendeeRepo,
        private readonly TicketRepository $ticketRepo,
    ) {
    }

    /**
     * Berechnet alle Warnungen für einen User.
     *
     * @return array{
     *     noResponse: list<array{concert: Concert}>,
     *     noTicket: list<array{concert: Concert, status: string}>,
     *     unassignedTickets: list<array{concert: Concert, count: int}>,
     *     unpaidDebts: list<array{concert: Concert, debtor: string, creditor: string, amount: string, isPast: bool}>,
     *     concertWarnings: array<string, list<string>>
     * }
     */
    public function getWarningsForUser(User $user): array
    {
        $warnings = [
            'noResponse' => [],
            'noTicket' => [],
            'unassignedTickets' => [],
            'unpaidDebts' => [],
            'concertWarnings' => [], // concertId => list of warning keys for badge display
        ];

        $now = new \DateTimeImmutable('now');
        $soonThreshold = $now->modify('+' . self::SOON_DAYS . ' days');

        // A1: Upcoming published concerts within 7 days where user has no attendee record
        $this->checkNoResponse($user, $now, $soonThreshold, $warnings);

        // B1: User is ATTENDING/INTERESTED but has no ticket (< 7 days)
        $this->checkNoTicket($user, $now, $soonThreshold, $warnings);

        // C1: Tickets without owner (< 7 days), visible to all users
        $this->checkUnassignedTickets($now, $soonThreshold, $warnings);

        // E1+E2: Unpaid tickets with debt involving this user
        $this->checkUnpaidDebts($user, $now, $warnings);

        return $warnings;
    }

    /**
     * A1: Konzert < 7 Tage, User hat keine Teilnahme-Aussage.
     */
    private function checkNoResponse(User $user, \DateTimeImmutable $now, \DateTimeImmutable $soonThreshold, array &$warnings): void
    {
        $upcomingSoon = $this->concertRepo->findPublishedBetween($now, $soonThreshold);

        foreach ($upcomingSoon as $concert) {
            $attendee = $this->attendeeRepo->findOneByUserAndConcert($user, $concert);
            if ($attendee === null) {
                $warnings['noResponse'][] = ['concert' => $concert];
                $warnings['concertWarnings'][$concert->getId()][] = 'noResponse';
            }
        }
    }

    /**
     * B1: User ist ATTENDING/INTERESTED, hat aber kein Ticket (nur Konzerte < 7 Tage).
     */
    private function checkNoTicket(User $user, \DateTimeImmutable $now, \DateTimeImmutable $soonThreshold, array &$warnings): void
    {
        // Find all upcoming concerts where user is attending or interested
        $attendeeRecords = $this->attendeeRepo->findUpcomingByUserWithStatuses(
            $user,
            [AttendeeStatus::ATTENDING, AttendeeStatus::INTERESTED]
        );

        foreach ($attendeeRecords as $attendee) {
            $concert = $attendee->getConcert();
            if ($concert->getWhenAt() < $now || $concert->getWhenAt() > $soonThreshold) {
                continue;
            }
            if ($concert->getStatus() !== ConcertStatus::PUBLISHED) {
                continue;
            }

            $hasTicket = $this->ticketRepo->hasTicketForConcert($user, $concert);
            if (!$hasTicket) {
                $statusStr = $attendee->getStatus() === AttendeeStatus::ATTENDING ? 'ATTENDING' : 'INTERESTED';
                $warnings['noTicket'][] = [
                    'concert' => $concert,
                    'status' => $statusStr,
                ];
                $warnings['concertWarnings'][$concert->getId()][] = 'noTicket';
            }
        }
    }

    /**
     * C1: Tickets ohne Owner bei kommenden Konzerten (< 7 Tage), für alle User sichtbar.
     */
    private function checkUnassignedTickets(\DateTimeImmutable $now, \DateTimeImmutable $soonThreshold, array &$warnings): void
    {
        $tickets = $this->ticketRepo->findUnassignedUpcoming($now, $soonThreshold);

        // Group by concert
        $byConcert = [];
        foreach ($tickets as $ticket) {
            $concert = $ticket->getConcert();
            $concertId = $concert->getId();
            if (!isset($byConcert[$concertId])) {
                $byConcert[$concertId] = ['concert' => $concert, 'count' => 0];
            }
            $byConcert[$concertId]['count']++;
        }

        foreach ($byConcert as $concertId => $data) {
            $warnings['unassignedTickets'][] = $data;
            $warnings['concertWarnings'][$concertId][] = 'unassignedTicket';
        }
    }

    /**
     * E1+E2: Unbezahlte Tickets mit Schulden, die den User betreffen.
     */
    private function checkUnpaidDebts(User $user, \DateTimeImmutable $now, array &$warnings): void
    {
        $tickets = $this->ticketRepo->findUnpaidDebtsForUser($user);

        foreach ($tickets as $ticket) {
            $concert = $ticket->getConcert();
            $isPast = $concert->getWhenAt() < $now;

            // Determine who owes whom
            $ownerName = $ticket->getOwnerDisplayName() ?? 'Unbekannt';
            $purchaserName = $ticket->getPurchaserDisplayName() ?? 'Unbekannt';

            $warnings['unpaidDebts'][] = [
                'concert' => $concert,
                'debtor' => $ownerName,
                'creditor' => $purchaserName,
                'amount' => $ticket->getPrice() ?? '0.00',
                'isPast' => $isPast,
            ];

            $concertId = $concert->getId();
            if (!in_array('unpaidDebt', $warnings['concertWarnings'][$concertId] ?? [], true)) {
                $warnings['concertWarnings'][$concertId][] = 'unpaidDebt';
            }
        }
    }
}
