<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Concert;
use App\Entity\Ticket;
use App\Enum\AttendeeStatus;
use App\Form\TicketType;
use App\Repository\ConcertAttendeeRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use MyFramework\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/concert/{concertId}/ticket')]
#[IsGranted('ROLE_USER')]
final class TicketController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TicketRepository $ticketRepo,
        private readonly ConcertAttendeeRepository $attendeeRepo,
    ) {
    }

    /**
     * Mobile-first full-page form für neues Ticket.
     */
    #[Route('/new', name: 'ticket_new', methods: ['GET', 'POST'])]
    public function new(string $concertId, Request $request): Response
    {
        $concert = $this->em->find(Concert::class, $concertId);
        if (!$concert) {
            throw $this->createNotFoundException('Konzert nicht gefunden');
        }

        /** @var User $user */
        $user = $this->getUser();

        // Build attendee choices (users who are ATTENDING or INTERESTED)
        $attendeeChoices = $this->buildAttendeeChoices($concert, $user);

        $ticket = new Ticket($concert, $user);
        $ticket->setCreatedBy($user);

        $form = $this->createForm(TicketType::class, $ticket, [
            'attendee_choices' => $attendeeChoices,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get owner and purchaser from unmapped fields
            $ownerId = $form->get('ownerId')->getData();
            $purchaserId = $form->get('purchaserId')->getData();

            $userRepo = $this->em->getRepository(User::class);
            $owner = $ownerId ? $userRepo->find($ownerId) : null;
            $purchaser = $purchaserId ? $userRepo->find($purchaserId) : null;

            // Validate that if IDs are provided, users exist
            if (($ownerId && !$owner) || ($purchaserId && !$purchaser)) {
                $this->addFlash('error', 'Ungültiger Benutzer ausgewählt');
                return $this->redirectToRoute('ticket_new', ['concertId' => $concertId]);
            }

            $ticket->setOwner($owner);
            $ticket->setPurchaser($purchaser);

            // If owner == purchaser and both exist, ticket is considered paid (no debt)
            if ($owner && $purchaser && $owner->getId() === $purchaser->getId()) {
                $ticket->setIsPaid(true);
            }

            $this->em->persist($ticket);
            $this->em->flush();

            $this->addFlash('success', 'Ticket wurde hinzugefügt! 🎟️');
            return $this->redirectToRoute('concert_show', ['id' => $concertId]);
        }

        return $this->render('ticket/new.html.twig', [
            'concert' => $concert,
            'form' => $form,
        ]);
    }

    /**
     * Edit existing ticket.
     */
    #[Route('/{ticketId}/edit', name: 'ticket_edit', methods: ['GET', 'POST'])]
    public function edit(string $concertId, string $ticketId, Request $request): Response
    {
        $concert = $this->em->find(Concert::class, $concertId);
        if (!$concert) {
            throw $this->createNotFoundException('Konzert nicht gefunden');
        }

        $ticket = $this->ticketRepo->find($ticketId);
        if (!$ticket) {
            throw $this->createNotFoundException('Ticket nicht gefunden');
        }

        // Everyone can edit tickets
        /** @var User $user */
        $user = $this->getUser();

        // Build attendee choices
        $attendeeChoices = $this->buildAttendeeChoices($concert, $user);

        $form = $this->createForm(TicketType::class, $ticket, [
            'attendee_choices' => $attendeeChoices,
        ]);

        // Pre-fill owner and purchaser IDs
        $form->get('ownerId')->setData($ticket->getOwner()?->getId());
        $form->get('purchaserId')->setData($ticket->getPurchaser()?->getId());

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get owner and purchaser from unmapped fields
            $ownerId = $form->get('ownerId')->getData();
            $purchaserId = $form->get('purchaserId')->getData();

            $userRepo = $this->em->getRepository(User::class);
            $owner = $ownerId ? $userRepo->find($ownerId) : null;
            $purchaser = $purchaserId ? $userRepo->find($purchaserId) : null;

            // Validate that if IDs are provided, users exist
            if (($ownerId && !$owner) || ($purchaserId && !$purchaser)) {
                $this->addFlash('error', 'Ungültiger Benutzer ausgewählt');
                return $this->redirectToRoute('ticket_edit', [
                    'concertId' => $concertId,
                    'ticketId' => $ticketId,
                ]);
            }

            $ticket->setOwner($owner);
            $ticket->setPurchaser($purchaser);

            // If owner == purchaser and both exist, ticket is considered paid (no debt)
            if ($owner && $purchaser && $owner->getId() === $purchaser->getId()) {
                $ticket->setIsPaid(true);
            }

            $this->em->flush();

            $this->addFlash('success', 'Ticket wurde aktualisiert! 🎟️');
            return $this->redirectToRoute('concert_show', ['id' => $concertId]);
        }

        return $this->render('ticket/edit.html.twig', [
            'concert' => $concert,
            'ticket' => $ticket,
            'form' => $form,
        ]);
    }

    /**
     * Markiert ein Ticket als bezahlt (AJAX).
     */
    #[Route('/{ticketId}/pay', name: 'ticket_mark_paid', methods: ['POST'])]
    public function markPaid(string $concertId, string $ticketId, Request $request): JsonResponse
    {
        $ticket = $this->ticketRepo->find($ticketId);
        if (!$ticket) {
            return $this->json(['error' => 'Ticket nicht gefunden'], Response::HTTP_NOT_FOUND);
        }

        // Everyone can mark tickets as paid
        $ticket->setIsPaid(true);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Zahlung als erledigt markiert ✅',
        ]);
    }

    /**
     * Löscht ein Ticket (AJAX).
     */
    #[Route('/{ticketId}/delete', name: 'ticket_delete', methods: ['POST', 'DELETE'])]
    public function delete(string $concertId, string $ticketId): JsonResponse
    {
        $ticket = $this->ticketRepo->find($ticketId);
        if (!$ticket) {
            return $this->json(['error' => 'Ticket nicht gefunden'], Response::HTTP_NOT_FOUND);
        }

        // Everyone can delete tickets
        $this->em->remove($ticket);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Ticket wurde gelöscht 🗑️',
        ]);
    }

    /**
     * Lädt die Ticket-Liste (Turbo Frame).
     */
    #[Route('/list', name: 'ticket_list', methods: ['GET'])]
    public function list(string $concertId): Response
    {
        $concert = $this->em->find(Concert::class, $concertId);
        if (!$concert) {
            throw $this->createNotFoundException('Konzert nicht gefunden');
        }

        $tickets = $this->ticketRepo->findByConcertWithUsers($concert);
        $unpaidTickets = $this->ticketRepo->findUnpaidWithDebt($concert);

        /** @var User $user */
        $user = $this->getUser();

        return $this->render('ticket/_list.html.twig', [
            'concert' => $concert,
            'tickets' => $tickets,
            'unpaidTickets' => $unpaidTickets,
            'currentUser' => $user,
        ]);
    }

    /**
     * Builds the choices array for the owner/purchaser dropdowns.
     * Includes current user + all attendees with ATTENDING or INTERESTED status.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function buildAttendeeChoices(Concert $concert, User $currentUser): array
    {
        $choices = [];
        $addedIds = [];

        // Always add current user first
        $choices[] = [
            'id' => $currentUser->getId(),
            'name' => $currentUser->getDisplayName() . ' (Ich)',
        ];
        $addedIds[$currentUser->getId()] = true;

        // Add all active attendees
        $attendees = $this->attendeeRepo->findActiveAttendees($concert);
        foreach ($attendees as $attendee) {
            $attendeeUser = $attendee->getUser();
            if ($attendeeUser && !isset($addedIds[$attendeeUser->getId()])) {
                $choices[] = [
                    'id' => $attendeeUser->getId(),
                    'name' => $attendeeUser->getDisplayName(),
                ];
                $addedIds[$attendeeUser->getId()] = true;
            }
        }

        return $choices;
    }
}
