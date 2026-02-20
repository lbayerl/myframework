<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Concert;
use App\Entity\ConcertAttendee;
use App\Entity\Guest;
use App\Enum\AttendeeStatus;
use App\Repository\ConcertAttendeeRepository;
use App\Repository\GuestRepository;
use Doctrine\ORM\EntityManagerInterface;
use MyFramework\Core\Entity\User;
use MyFramework\Core\Push\Service\PushService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/concert/{id}/attendee')]
#[IsGranted('ROLE_USER')]
final class AttendeeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConcertAttendeeRepository $attendeeRepo,
        private readonly GuestRepository $guestRepo,
        private readonly PushService $pushService,
    ) {
    }

    /**
     * Setzt oder ändert den Teilnahme-Status des aktuellen Users für ein Konzert.
     * Erwartet JSON: {"status": "ATTENDING"|"INTERESTED"|"DECLINED"}
     */
    #[Route('/status', name: 'concert_attendee_status', methods: ['POST'])]
    public function updateStatus(Concert $concert, Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Nicht angemeldet'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $statusValue = $data['status'] ?? null;

        if (!$statusValue) {
            return $this->json(['error' => 'Status fehlt'], Response::HTTP_BAD_REQUEST);
        }

        // Validate status
        $status = AttendeeStatus::tryFrom($statusValue);
        if (!$status) {
            return $this->json(['error' => 'Ungültiger Status'], Response::HTTP_BAD_REQUEST);
        }

        // Find existing attendee record or create new one
        $attendee = $this->attendeeRepo->findOneByUserAndConcert($user, $concert);

        if ($attendee) {
            // Update existing status (touch() is called automatically by setStatus())
            $attendee->setStatus($status);
        } else {
            // Create new attendee record
            $attendee = new ConcertAttendee($concert, $user, $status);
            $this->em->persist($attendee);
        }

        $this->em->flush();

        // Notify other ATTENDING users when someone new joins
        if ($status === AttendeeStatus::ATTENDING) {
            $this->notifyAttendingUsers($concert, $user);
        }

        // Generate message for toast
        $message = match ($status) {
            AttendeeStatus::ATTENDING => 'Du bist jetzt dabei! 🎉',
            AttendeeStatus::INTERESTED => 'Du bist als interessiert markiert',
            AttendeeStatus::DECLINED => 'Schade, vielleicht nächstes Mal!',
            AttendeeStatus::PARTICIPATED => 'Teilnahme vermerkt',
        };

        // Return updated attendee list as Turbo Stream
        $attendees = $this->attendeeRepo->findByConcertSorted($concert);

        return $this->json([
            'success' => true,
            'status' => $status->value,
            'message' => $message,
            'html' => $this->renderView('attendee/_list.html.twig', [
                'concert' => $concert,
                'attendees' => $attendees,
            ]),
        ]);
    }

    /**
     * Gibt die Teilnehmerliste eines Konzerts als HTML-Partial zurück (für Turbo Frame).
     */
    #[Route('/list', name: 'concert_attendee_list', methods: ['GET'])]
    public function list(Concert $concert): Response
    {
        $attendees = $this->attendeeRepo->findByConcertSorted($concert);

        return $this->render('attendee/_list.html.twig', [
            'concert' => $concert,
            'attendees' => $attendees,
        ]);
    }

    /**
     * Setzt oder ändert den Teilnahme-Status eines Gasts für ein Konzert.
     * Erwartet JSON: {"guest_id": 123, "status": "ATTENDING"|"INTERESTED"|"DECLINED"}
     */
    #[Route('/guest-status', name: 'concert_attendee_guest_status', methods: ['POST'])]
    public function updateGuestStatus(Concert $concert, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $guestId = $data['guest_id'] ?? null;
        $statusValue = $data['status'] ?? null;

        if (!$guestId || !$statusValue) {
            return $this->json(['error' => 'Gast-ID und Status erforderlich'], Response::HTTP_BAD_REQUEST);
        }

        $status = AttendeeStatus::tryFrom($statusValue);
        if (!$status) {
            return $this->json(['error' => 'Ungültiger Status'], Response::HTTP_BAD_REQUEST);
        }

        $guest = $this->guestRepo->find($guestId);
        if (!$guest) {
            return $this->json(['error' => 'Gast nicht gefunden'], Response::HTTP_NOT_FOUND);
        }

        $attendee = $this->attendeeRepo->findOneByGuestAndConcert($guest, $concert);

        if ($attendee) {
            $attendee->setStatus($status);
        } else {
            $attendee = new ConcertAttendee($concert, $guest, $status);
            $this->em->persist($attendee);
        }

        $this->em->flush();

        $message = match ($status) {
            AttendeeStatus::ATTENDING => sprintf('%s ist jetzt dabei! 🎉', $guest->getDisplayName()),
            AttendeeStatus::INTERESTED => sprintf('%s als interessiert markiert', $guest->getDisplayName()),
            AttendeeStatus::DECLINED => sprintf('%s hat abgesagt', $guest->getDisplayName()),
            AttendeeStatus::PARTICIPATED => sprintf('%s: Teilnahme vermerkt', $guest->getDisplayName()),
        };

        $attendees = $this->attendeeRepo->findByConcertSorted($concert);

        return $this->json([
            'success' => true,
            'status' => $status->value,
            'message' => $message,
            'html' => $this->renderView('attendee/_list.html.twig', [
                'concert' => $concert,
                'attendees' => $attendees,
            ]),
        ]);
    }

    /**
     * Entfernt einen Gast von der Teilnehmerliste.
     */
    #[Route('/guest-remove', name: 'concert_attendee_guest_remove', methods: ['POST'])]
    public function removeGuest(Concert $concert, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $guestId = $data['guest_id'] ?? null;

        if (!$guestId) {
            return $this->json(['error' => 'Gast-ID erforderlich'], Response::HTTP_BAD_REQUEST);
        }

        $guest = $this->guestRepo->find($guestId);
        if (!$guest) {
            return $this->json(['error' => 'Gast nicht gefunden'], Response::HTTP_NOT_FOUND);
        }

        $attendee = $this->attendeeRepo->findOneByGuestAndConcert($guest, $concert);
        if ($attendee) {
            $this->em->remove($attendee);
            $this->em->flush();
        }

        $attendees = $this->attendeeRepo->findByConcertSorted($concert);

        return $this->json([
            'success' => true,
            'message' => sprintf('%s wurde von der Teilnehmerliste entfernt', $guest->getDisplayName()),
            'html' => $this->renderView('attendee/_list.html.twig', [
                'concert' => $concert,
                'attendees' => $attendees,
            ]),
        ]);
    }

    /**
     * Notify all ATTENDING users (except the one who just joined) that someone new is coming.
     */
    private function notifyAttendingUsers(Concert $concert, User $newAttendee): void
    {
        $attendees = $this->attendeeRepo->findByConcertAndStatus($concert, AttendeeStatus::ATTENDING);
        $url = $this->generateUrl('concert_show', ['id' => $concert->getId()]);

        foreach ($attendees as $attendee) {
            $user = $attendee->getUser();
            if ($user !== null && $user->getId() !== $newAttendee->getId()) {
                $this->pushService->sendToUser(
                    $user,
                    sprintf('%s: Neue Zusage', $concert->getTitle()),
                    sprintf('%s ist jetzt auch dabei!', $newAttendee->getDisplayName()),
                    $url,
                );
            }
        }
    }
}
