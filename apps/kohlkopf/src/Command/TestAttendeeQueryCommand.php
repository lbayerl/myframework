<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ConcertAttendeeRepository;
use App\Repository\ConcertRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-attendee-query',
    description: 'Test attendee query with UUID',
)]
final class TestAttendeeQueryCommand extends Command
{
    public function __construct(
        private readonly ConcertRepository $concertRepo,
        private readonly ConcertAttendeeRepository $attendeeRepo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $concert = $this->concertRepo->findOneBy([], ['whenAt' => 'DESC']);
        
        if (!$concert) {
            $io->error('No concert found');
            return Command::FAILURE;
        }

        $io->title('Testing Attendee Query');
        $io->writeln('Concert ID: ' . $concert->getId());
        $io->writeln('Concert ID type: ' . gettype($concert->getId()));
        $io->writeln('Concert Title: ' . $concert->getTitle());
        
        // Test raw SQL first
        $em = $this->concertRepo->getEntityManager();
        $conn = $em->getConnection();
        $rawResult = $conn->executeQuery(
            'SELECT * FROM concert_attendee WHERE concert_id = ?',
            [$concert->getId()],
            [\Doctrine\DBAL\ParameterType::BINARY]
        )->fetchAllAssociative();
        
        $io->writeln('Raw SQL found: ' . count($rawResult) . ' rows');
        
        $attendees = $this->attendeeRepo->findByConcertSorted($concert);
        
        $io->writeln('Found ' . count($attendees) . ' attendees');
        
        foreach ($attendees as $attendee) {
            $io->writeln(sprintf(
                '- %s (%s)',
                $attendee->getUser()->getEmail(),
                $attendee->getStatus()->value
            ));
        }

        return Command::SUCCESS;
    }
}
