<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Concert;
use App\Entity\Guest;
use App\Entity\Ticket;
use App\Form\TicketType;
use App\Repository\GuestRepository;
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
        private readonly GuestRepository $guestRepo,
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

        // Build person choices: all users + all guests (independent of attendance)
        $personChoices = $this->buildPersonChoices($user);

        $ticket = new Ticket($concert, $user);
        $ticket->setCreatedBy($user);

        $form = $this->createForm(TicketType::class, $ticket, [
            'attendee_choices' => $personChoices,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->applyPersonSelection($ticket, $form)) {
                $this->addFlash('error', 'Ungültige Person ausgewählt');
                return $this->redirectToRoute('ticket_new', ['concertId' => $concertId]);
            }

            // If same person owns and purchased, auto-mark as paid
            if (!$ticket->hasDebt()) {
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

        /** @var User $user */
        $user = $this->getUser();

        // Build person choices: all users + all guests
        $personChoices = $this->buildPersonChoices($user);

        $form = $this->createForm(TicketType::class, $ticket, [
            'attendee_choices' => $personChoices,
        ]);

        // Pre-fill owner and purchaser IDs (prefixed with user_ or guest_)
        $form->get('ownerId')->setData($this->getPersonKey($ticket->getOwner(), $ticket->getGuestOwner()));
        $form->get('purchaserId')->setData($this->getPersonKey($ticket->getPurchaser(), $ticket->getGuestPurchaser()));

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->applyPersonSelection($ticket, $form)) {
                $this->addFlash('error', 'Ungültige Person ausgewählt');
                return $this->redirectToRoute('ticket_edit', [
                    'concertId' => $concertId,
                    'ticketId' => $ticketId,
                ]);
            }

            // If same person owns and purchased, auto-mark as paid
            if (!$ticket->hasDebt()) {
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
     * Includes current user + ALL registered users + all active guests.
     * Ticket assignment is independent of attendance status.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private function buildPersonChoices(User $currentUser): array
    {
        $choices = [];
        $addedKeys = [];

        // Always add current user first
        $key = 'user_' . $currentUser->getId();
        $choices[] = [
            'id' => $key,
            'name' => $currentUser->getDisplayName() . ' (Ich)',
        ];
        $addedKeys[$key] = true;

        // Add ALL registered users
        $allUsers = $this->em->getRepository(User::class)->findAll();
        foreach ($allUsers as $user) {
            $key = 'user_' . $user->getId();
            if (!isset($addedKeys[$key])) {
                $choices[] = [
                    'id' => $key,
                    'name' => $user->getDisplayName(),
                ];
                $addedKeys[$key] = true;
            }
        }

        // Add all active (non-converted) guests
        $guests = $this->guestRepo->findAllActive();
        foreach ($guests as $guest) {
            $key = 'guest_' . $guest->getId();
            $choices[] = [
                'id' => $key,
                'name' => $guest->getName() . ' (Gast)',
            ];
        }

        return $choices;
    }

    /**
     * Returns a prefixed key like "user_123" or "guest_45" for the person dropdowns.
     */
    private function getPersonKey(?User $user, ?Guest $guest): ?string
    {
        if ($user !== null) {
            return 'user_' . $user->getId();
        }
        if ($guest !== null) {
            return 'guest_' . $guest->getId();
        }
        return null;
    }

    /**
     * Parse the selected person IDs and apply them to the ticket.
     * IDs are prefixed: "user_123" or "guest_45".
     * Returns false if any referenced entity was not found.
     */
    private function applyPersonSelection(Ticket $ticket, \Symfony\Component\Form\FormInterface $form): bool
    {
        $ownerId = $form->get('ownerId')->getData();
        $purchaserId = $form->get('purchaserId')->getData();

        // Reset all person fields
        $ticket->setOwner(null);
        $ticket->setGuestOwner(null);
        $ticket->setPurchaser(null);
        $ticket->setGuestPurchaser(null);

        // Apply owner
        if ($ownerId) {
            if (!$this->applyPersonToTicket($ticket, (string) $ownerId, 'owner')) {
                return false;
            }
        }

        // Apply purchaser
        if ($purchaserId) {
            if (!$this->applyPersonToTicket($ticket, (string) $purchaserId, 'purchaser')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sets user or guest on the ticket based on the prefixed ID.
     * Returns false if the referenced entity was not found.
     */
    private function applyPersonToTicket(Ticket $ticket, string $prefixedId, string $role): bool
    {
        if (!str_contains($prefixedId, '_')) {
            return false;
        }

        [$type, $id] = explode('_', $prefixedId, 2);

        if ($type === 'user') {
            $user = $this->em->getRepository(User::class)->find((int) $id);
            if ($user === null) {
                return false;
            }
            if ($role === 'owner') {
                $ticket->setOwner($user);
            } else {
                $ticket->setPurchaser($user);
            }
        } elseif ($type === 'guest') {
            $guest = $this->guestRepo->find((int) $id);
            if ($guest === null) {
                return false;
            }
            if ($role === 'owner') {
                $ticket->setGuestOwner($guest);
            } else {
                $ticket->setGuestPurchaser($guest);
            }
        }

        return true;
    }
}
