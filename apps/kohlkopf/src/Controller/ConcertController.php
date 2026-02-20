<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Concert;
use App\Entity\Payment;
use App\Entity\Ticket;
use App\Enum\AttendeeStatus;
use App\Enum\ConcertStatus;
use App\Form\ConcertType;
use App\Repository\ConcertAttendeeRepository;
use App\Repository\ConcertRepository;
use App\Repository\GuestRepository;
use App\Repository\TicketRepository;
use App\Service\ArtistEnrichmentService;
use App\Service\ConcertWarningService;
use Doctrine\ORM\EntityManagerInterface;
use MyFramework\Core\Entity\User;
use MyFramework\Core\Push\Service\PushService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/concerts')]
final class ConcertController extends AbstractController
{
    public function __construct(
        private readonly ConcertAttendeeRepository $attendeeRepo,
        private readonly TicketRepository $ticketRepo,
        private readonly PushService $pushService,
        private readonly ArtistEnrichmentService $artistEnrichmentService,
        private readonly ConcertWarningService $warningService,
    ) {
    }

    #[Route('/new', name: 'concert_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $concert = new Concert();
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $concert->setCreatedBy($user);

        $form = $this->createForm(ConcertType::class, $concert);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $concert->touch();
            $em->persist($concert);
            $em->flush();

            // Enrich concert with artist data from MusicBrainz + Wikipedia
            $this->artistEnrichmentService->enrich($concert);
            $em->flush();

            $this->pushService->sendToAll(
                'Neues Konzert',
                sprintf('%s am %s um %s', $concert->getTitle(), $concert->getWhenAt()->format('d.m.Y'), $concert->getWhenAt()->format('H:i')),
                $this->generateUrl('concert_show', ['id' => $concert->getId()])
            );

            $this->addFlash('success', 'Konzert wurde gespeichert.');
            return $this->redirectToRoute('concert_show', ['id' => $concert->getId()]);
        }

        if ($form->isSubmitted()) {
            $this->addFlash('error', 'Bitte prüfe die Eingaben.');
        }

        return $this->render('concert/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'concert_edit', methods: ['GET', 'POST'])]
    public function edit(Concert $concert, Request $request, EntityManagerInterface $em): Response
    {
        // Rechte: Ersteller oder Admin
        $user = $this->getUser();
        if ($concert->getCreatedBy()?->getId() !== $user?->getId() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        // Remember original values to detect changes
        $originalTitle = $concert->getTitle();
        $originalStatus = $concert->getStatus();
        $originalWhenAt = clone $concert->getWhenAt();
        $originalWhereText = $concert->getWhereText();

        $form = $this->createForm(ConcertType::class, $concert);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Konsistenz: wenn Status != CANCELLED, cancelledAt nullen
            if ($concert->getStatus() !== ConcertStatus::CANCELLED) {
                $concert->setCancelledAt(null);
            }

            // If title changed, re-enrich artist data
            if ($concert->getTitle() !== $originalTitle) {
                $this->artistEnrichmentService->reEnrich($concert);
            }

            $concert->touch();
            $em->flush();

            // Notify attendees about cancellation
            if ($concert->getStatus() === ConcertStatus::CANCELLED && $originalStatus !== ConcertStatus::CANCELLED) {
                $this->notifyAttendees(
                    $concert,
                    'Konzert abgesagt',
                    sprintf('%s wurde leider abgesagt.', $concert->getTitle()),
                );
            }

            // Notify attendees about date/venue changes (only if not cancelled)
            if ($concert->getStatus() !== ConcertStatus::CANCELLED) {
                $changes = [];
                if ($concert->getWhenAt()->format('Y-m-d H:i') !== $originalWhenAt->format('Y-m-d H:i')) {
                    $changes[] = sprintf('Neuer Termin: %s um %s', $concert->getWhenAt()->format('d.m.Y'), $concert->getWhenAt()->format('H:i'));
                }
                if ($concert->getWhereText() !== $originalWhereText) {
                    $changes[] = sprintf('Neuer Ort: %s', $concert->getWhereText() ?: '(entfernt)');
                }
                if (count($changes) > 0) {
                    $this->notifyAttendees(
                        $concert,
                        sprintf('Konzert geändert: %s', $concert->getTitle()),
                        implode(' | ', $changes),
                    );
                }
            }

            $this->addFlash('success', 'Änderungen gespeichert.');
            return $this->redirectToRoute('concert_show', ['id' => $concert->getId()]);
        }

        if ($form->isSubmitted()) {
            $this->addFlash('error', 'Bitte prüfe die Eingaben.');
        }

        return $this->render('concert/edit.html.twig', [
            'form' => $form->createView(),
            'concert' => $concert,
        ]);
    }

    #[Route('/{id}', name: 'concert_show', methods: ['GET'], priority: -1)]
    public function show(Concert $concert, GuestRepository $guestRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get current user's attendance status
        $userAttendee = $this->attendeeRepo->findOneByUserAndConcert($user, $concert);
        $userStatus = $userAttendee?->getStatus();

        // Get all attendees (sorted by status)
        $attendees = $this->attendeeRepo->findByConcertSorted($concert);

        // Count by status
        $attendingCount = $this->attendeeRepo->countByStatus($concert, AttendeeStatus::ATTENDING);
        $interestedCount = $this->attendeeRepo->countByStatus($concert, AttendeeStatus::INTERESTED);

        // Get all tickets with users
        $tickets = $this->ticketRepo->findByConcertWithUsers($concert);

        // Get unpaid tickets (for debt display)
        $unpaidTickets = $this->ticketRepo->findUnpaidWithDebt($concert);

        // Check if user can edit
        $canEdit = $concert->getCreatedBy()?->getId() === $user->getId() || $this->isGranted('ROLE_ADMIN');

        // Get all active guests for the dropdown
        $guests = $guestRepo->findAllActive();

        return $this->render('concert/show.html.twig', [
            'concert' => $concert,
            'userStatus' => $userStatus,
            'attendees' => $attendees,
            'attendingCount' => $attendingCount,
            'interestedCount' => $interestedCount,
            'tickets' => $tickets,
            'unpaidTickets' => $unpaidTickets,
            'canEdit' => $canEdit,
            'currentUser' => $user,
            'guests' => $guests,
        ]);
    }

    #[Route('', name: 'concert_index', methods: ['GET'])]
    public function index(ConcertRepository $concertRepository): Response
    {
        $concertData = $concertRepository->findUpcomingWithCounts();

        return $this->render('concert/index.html.twig', [
            'concertData' => $concertData,
            'activeView' => 'upcoming',
        ]);
    }

    #[Route('/mine', name: 'concert_mine', methods: ['GET'])]
    public function mine(ConcertRepository $concertRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $concertData = $concertRepository->findUpcomingForUser($user->getId());
        $warnings = $this->warningService->getWarningsForUser($user);

        return $this->render('concert/mine.html.twig', [
            'concertData' => $concertData,
            'activeView' => 'mine',
            'warnings' => $warnings,
        ]);
    }

    #[Route('/past', name: 'concert_past', methods: ['GET'])]
    public function past(ConcertRepository $concertRepository): Response
    {
        $concertData = $concertRepository->findPast();

        return $this->render('concert/past.html.twig', [
            'concertData' => $concertData,
            'activeView' => 'past',
        ]);
    }

    #[Route('/all', name: 'concert_all', methods: ['GET'])]
    public function all(ConcertRepository $concertRepository): Response
    {
        $concertData = $concertRepository->findAll();

        return $this->render('concert/all.html.twig', [
            'concertData' => $concertData,
            'activeView' => 'all',
        ]);
    }

    #[Route('/{id}/calendar', name: 'concert_calendar', methods: ['GET'])]
    public function exportCalendar(Concert $concert): Response
    {
        // iCalendar Datum-Format: YYYYMMDDTHHMMSS (in UTC oder mit TZID)
        $start = $concert->getWhenAt();
        $end = (clone $start)->modify('+3 hours'); // Standard-Dauer

        // Format für iCal: YYYYMMDDTHHMMSSZ (UTC)
        $dtStart = $start->format('Ymd\THis');
        $dtEnd = $end->format('Ymd\THis');
        $dtStamp = (new \DateTime())->format('Ymd\THis\Z');

        // Escape-Funktion für iCal-Text (Kommas, Semikolons, Backslashes, Newlines)
        $escape = fn(string $text): string => str_replace(
            ['\\', ',', ';', "\n"],
            ['\\\\', '\\,', '\\;', '\\n'],
            $text
        );

        $summary = $escape($concert->getTitle());
        $location = $concert->getWhereText() ? $escape($concert->getWhereText()) : '';
        $description = $concert->getComment() ? $escape($concert->getComment()) : '';
        if ($concert->getExternalLink()) {
            $description .= ($description ? '\\n\\n' : '') . $escape($concert->getExternalLink());
        }

        $uid = 'concert-' . $concert->getId() . '@kohlkopf.local';

        // iCalendar-Datei generieren
        $ics = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Kohlkopf//Concert Export//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$dtStamp}",
            "DTSTART:{$dtStart}",
            "DTEND:{$dtEnd}",
            "SUMMARY:{$summary}",
        ];

        if ($location) {
            $ics[] = "LOCATION:{$location}";
        }

        if ($description) {
            $ics[] = "DESCRIPTION:{$description}";
        }

        if ($concert->getExternalLink()) {
            $ics[] = 'URL:' . $concert->getExternalLink();
        }

        $ics[] = 'END:VEVENT';
        $ics[] = 'END:VCALENDAR';

        $content = implode("\r\n", $ics);

        // Dateiname: Konzert-Titel sanitized
        $filename = preg_replace('/[^a-z0-9]+/i', '-', $concert->getTitle());
        $filename = trim($filename, '-') . '.ics';

        return new Response($content, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Delete a concert and all related entities (Admin only).
     */
    #[Route('/{id}/delete', name: 'concert_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Concert $concert, EntityManagerInterface $em): Response
    {
        $title = $concert->getTitle();

        // Delete payments linked to tickets of this concert
        $tickets = $em->getRepository(Ticket::class)->findBy(['concert' => $concert]);
        foreach ($tickets as $ticket) {
            $payments = $em->getRepository(Payment::class)->findBy(['ticket' => $ticket]);
            foreach ($payments as $payment) {
                $em->remove($payment);
            }
            $em->remove($ticket);
        }

        // Attendees are cascade-removed via Concert entity
        // Delete artist image if exists
        $this->artistEnrichmentService->deleteImage($concert->getArtistImage());

        $em->remove($concert);
        $em->flush();

        $this->addFlash('success', sprintf('Konzert „%s" wurde gelöscht 🗑️', $title));

        return $this->redirectToRoute('concert_index');
    }

    /**
     * Notify all ATTENDING + INTERESTED users about a concert change.
     */
    private function notifyAttendees(Concert $concert, string $title, string $body): void
    {
        $attendees = $this->attendeeRepo->findActiveAttendees($concert);
        $url = $this->generateUrl('concert_show', ['id' => $concert->getId()]);

        foreach ($attendees as $attendee) {
            $user = $attendee->getUser();
            if ($user !== null) {
                $this->pushService->sendToUser($user, $title, $body, $url);
            }
        }
    }
}
