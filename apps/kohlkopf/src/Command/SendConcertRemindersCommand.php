<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ConcertAttendeeRepository;
use App\Repository\ConcertRepository;
use MyFramework\Core\Entity\User;
use MyFramework\Core\Push\Service\PushService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(
    name: 'app:send-concert-reminders',
    description: 'Send daily push reminders for concerts in the next 14 days where users have not responded',
)]
final class SendConcertRemindersCommand extends Command
{
    public function __construct(
        private readonly ConcertRepository $concertRepository,
        private readonly ConcertAttendeeRepository $attendeeRepository,
        private readonly EntityManagerInterface $em,
        private readonly PushService $pushService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $now = new \DateTimeImmutable('now');
        $in14Days = $now->modify('+14 days');

        $concerts = $this->concertRepository->findPublishedBetween($now, $in14Days);

        if (count($concerts) === 0) {
            $io->info('No upcoming concerts in the next 14 days.');

            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d upcoming concerts in the next 14 days.', count($concerts)));

        // Get all users who have at least one push subscription
        $allUsers = $this->em->getRepository(User::class)->findAll();
        $notificationsSent = 0;

        foreach ($concerts as $concert) {
            $concertUrl = $this->urlGenerator->generate(
                'concert_show',
                ['id' => $concert->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            foreach ($allUsers as $user) {
                // Check if user has any attendee record for this concert
                $attendee = $this->attendeeRepository->findOneByUserAndConcert($user, $concert);
                if ($attendee !== null) {
                    // User already responded (ATTENDING, INTERESTED, DECLINED, etc.)
                    continue;
                }

                // Check if user has push subscriptions (no point sending to users without)
                $subscriptions = $this->pushService->getSubscriptionsByUser($user);
                if (count($subscriptions) === 0) {
                    continue;
                }

                $daysUntil = (int) $now->diff($concert->getWhenAt())->days;
                $daysText = $daysUntil === 0 ? 'Heute' : ($daysUntil === 1 ? 'Morgen' : sprintf('In %d Tagen', $daysUntil));

                $this->pushService->sendToUser(
                    $user,
                    sprintf('%s: %s', $daysText, $concert->getTitle()),
                    sprintf('%s am %s – Du hast noch nicht zu-/abgesagt.', $concert->getTitle(), $concert->getWhenAt()->format('d.m.Y')),
                    $concertUrl,
                );

                $notificationsSent++;
                $io->text(sprintf(
                    '  → Reminder to %s for "%s" (%s)',
                    $user->getDisplayName(),
                    $concert->getTitle(),
                    $daysText,
                ));
            }
        }

        $io->success(sprintf('Sent %d reminder notifications.', $notificationsSent));

        return Command::SUCCESS;
    }
}
