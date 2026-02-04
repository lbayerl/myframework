<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Concert;
use App\Entity\ConcertAttendee;
use App\Enum\AttendeeStatus;
use App\Repository\ConcertAttendeeRepository;
use Doctrine\ORM\EntityManagerInterface;
use MyFramework\Core\Entity\User;
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
}
