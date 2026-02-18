<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Guest;
use App\Form\GuestFormType;
use App\Repository\GuestRepository;
use Doctrine\ORM\EntityManagerInterface;
use MyFramework\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/guests')]
#[IsGranted('ROLE_USER')]
final class GuestController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GuestRepository $guestRepo,
    ) {
    }

    /**
     * List all guests.
     */
    #[Route('', name: 'guest_index', methods: ['GET'])]
    public function index(): Response
    {
        $guests = $this->guestRepo->findAllSorted();

        return $this->render('guest/index.html.twig', [
            'guests' => $guests,
        ]);
    }

    /**
     * Create a new guest.
     */
    #[Route('/new', name: 'guest_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $guest = new Guest();
        $guest->setCreatedBy($user);

        $form = $this->createForm(GuestFormType::class, $guest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($guest);
            $this->em->flush();

            $this->addFlash('success', 'Gast wurde hinzugefügt! 👤');
            return $this->redirectToRoute('guest_index');
        }

        return $this->render('guest/new.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Edit an existing guest.
     */
    #[Route('/{id}/edit', name: 'guest_edit', methods: ['GET', 'POST'])]
    public function edit(Guest $guest, Request $request): Response
    {
        $form = $this->createForm(GuestFormType::class, $guest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $guest->touch();
            $this->em->flush();

            $this->addFlash('success', 'Gast wurde aktualisiert! 👤');
            return $this->redirectToRoute('guest_index');
        }

        return $this->render('guest/edit.html.twig', [
            'guest' => $guest,
            'form' => $form,
        ]);
    }

    /**
     * Delete a guest (AJAX).
     */
    #[Route('/{id}/delete', name: 'guest_delete', methods: ['POST', 'DELETE'])]
    public function delete(Guest $guest): Response
    {
        $this->em->remove($guest);
        $this->em->flush();

        $this->addFlash('success', 'Gast wurde gelöscht 🗑️');
        return $this->redirectToRoute('guest_index');
    }

    /**
     * Search guests by name (AJAX endpoint for autocomplete).
     */
    #[Route('/search', name: 'guest_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->getString('q', '');

        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $guests = $this->guestRepo->searchByName($query);

        $results = array_map(fn(Guest $g) => [
            'id' => $g->getId(),
            'name' => $g->getName(),
            'email' => $g->getEmail(),
        ], $guests);

        return $this->json($results);
    }
}
