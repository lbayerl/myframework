<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Concert;
use App\Enum\AttendeeStatus;
use App\Enum\ConcertStatus;
use App\Form\ConcertType;
use App\Repository\ConcertAttendeeRepository;
use App\Repository\ConcertRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use MyFramework\Core\Entity\User;
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

        // prefill unmapped date/time from entity (constructor set a sensible future placeholder)
        if ($concert->getWhenAt() instanceof \DateTimeInterface) {
            try {
                $form->get('date')->setData($concert->getWhenAt()->format('Y-m-d'));
                $form->get('time')->setData($concert->getWhenAt()->format('H:i'));
            } catch (\Exception) {
                // ignore; best-effort prefilling
            }
        }

        $form->handleRequest($request);

        // Datum und Uhrzeit aus unmapped Feldern zusammenführen
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $date = $form->get('date')->getData();
                $time = $form->get('time')->getData();
                if ($date && $time) {
                    $concert->setWhenAt(new \DateTime($date . 'T' . $time));
                }
                $concert->touch();
                $em->persist($concert);
                $em->flush();

                $this->addFlash('success', 'Konzert wurde gespeichert.');
                return $this->redirectToRoute('concert_show', ['id' => $concert->getId()]);
            }

            // nur anzeigen, wenn Formular abgeschickt und ungültig
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

        $form = $this->createForm(ConcertType::class, $concert);

        // prefill unmapped date/time from entity
        if ($concert->getWhenAt() instanceof \DateTimeInterface) {
            try {
                $form->get('date')->setData($concert->getWhenAt()->format('Y-m-d'));
                $form->get('time')->setData($concert->getWhenAt()->format('H:i'));
            } catch (\Exception) {
                // ignore
            }
        }

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $date = $form->get('date')->getData();
                $time = $form->get('time')->getData();
                if ($date && $time) {
                    $concert->setWhenAt(new \DateTime($date . 'T' . $time));
                }

                // Konsistenz: wenn Status != CANCELLED, cancelledAt nullen
                if ($concert->getStatus() !== ConcertStatus::CANCELLED) {
                    $concert->setCancelledAt(null);
                }
                $concert->touch();
                $em->flush();

                $this->addFlash('success', 'Änderungen gespeichert.');
                return $this->redirectToRoute('concert_show', ['id' => $concert->getId()]);
            }
            $this->addFlash('error', 'Bitte prüfe die Eingaben.');
        }

        return $this->render('concert/edit.html.twig', [
            'form' => $form->createView(),
            'concert' => $concert,
        ]);
    }

    #[Route('/{id}', name: 'concert_show', methods: ['GET'])]
    public function show(Concert $concert): Response
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
        ]);
    }

    #[Route('', name: 'concert_index', methods: ['GET'])]
    public function index(ConcertRepository $concertRepository): Response
    {
        $concerts = $concertRepository->findUpcoming();

        return $this->render('concert/index.html.twig', [
            'concerts' => $concerts,
        ]);
    }
}
