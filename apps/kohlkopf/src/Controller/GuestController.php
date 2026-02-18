<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Guest;
use App\Form\GuestFormType;
use App\Repository\GuestRepository;
use App\Service\GuestConversionService;
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
        private readonly GuestConversionService $conversionService,
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

    /**
     * Search users by name or email (AJAX endpoint for admin conversion).
     */
    #[Route('/user-search', name: 'guest_user_search', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function userSearch(Request $request): JsonResponse
    {
        $query = $request->query->getString('q', '');

        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $users = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('LOWER(u.email) LIKE LOWER(:q)')
            ->orWhere('LOWER(u.displayName) LIKE LOWER(:q)')
            ->setParameter('q', '%' . $query . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $results = array_map(fn(User $u) => [
            'id' => $u->getId(),
            'displayName' => $u->getDisplayName(),
            'email' => $u->getEmail(),
        ], $users);

        return $this->json($results);
    }

    /**
     * Manually link a guest to an existing user (Admin only).
     */
    #[Route('/{id}/convert', name: 'guest_convert', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function convert(Guest $guest, Request $request): Response
    {
        if ($guest->isConverted()) {
            $this->addFlash('warning', 'Gast ist bereits verknüpft.');
            return $this->redirectToRoute('guest_index');
        }

        $userId = $request->request->getInt('user_id');
        if ($userId <= 0) {
            $this->addFlash('error', 'Kein Benutzer ausgewählt.');
            return $this->redirectToRoute('guest_index');
        }

        $user = $this->em->getRepository(User::class)->find($userId);
        if ($user === null) {
            $this->addFlash('error', 'Benutzer nicht gefunden.');
            return $this->redirectToRoute('guest_index');
        }

        $this->conversionService->convertGuestToUser($guest, $user);

        $this->addFlash('success', sprintf(
            'Gast „%s" wurde mit %s verknüpft! ✅',
            $guest->getName(),
            $user->getDisplayName(),
        ));

        return $this->redirectToRoute('guest_index');
    }
}
