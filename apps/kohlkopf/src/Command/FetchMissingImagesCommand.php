<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ConcertRepository;
use App\Service\ArtistEnrichmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:enrich-concerts',
    description: 'Enrich concerts with artist data from MusicBrainz + Wikipedia (image, genres, description)',
)]
final class FetchMissingImagesCommand extends Command
{
    public function __construct(
        private readonly ConcertRepository $concertRepository,
        private readonly ArtistEnrichmentService $enrichmentService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Re-enrich all concerts, even those already enriched');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $concertData = $this->concertRepository->findAll();
        $io->info(sprintf('Found %d concerts', count($concertData)));

        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($concertData as $item) {
            $concert = $item['concert'];
            $title = $concert->getTitle();

            // Skip if already enriched (has MBID or description) unless --force
            if (!$force && ($concert->getMbid() !== null || $concert->getArtistDescription() !== null)) {
                $io->text(sprintf('⏭ Skipping "%s" (already enriched)', $title));
                $skipped++;
                continue;
            }

            $io->text(sprintf('🔍 Enriching "%s"...', $title));

            if ($force) {
                $this->enrichmentService->reEnrich($concert);
            } else {
                $this->enrichmentService->enrich($concert);
            }

            if ($concert->getMbid() !== null || $concert->getArtistImage() !== null) {
                $parts = [];
                if ($concert->getMbid() !== null) {
                    $parts[] = 'MBID';
                }
                if ($concert->getGenres() !== null) {
                    $parts[] = implode(', ', $concert->getGenres());
                }
                if ($concert->getArtistImage() !== null) {
                    $parts[] = 'image';
                }
                $io->text(sprintf('  ✅ %s', implode(' | ', $parts)));
                $updated++;
            } else {
                $io->text('  ❌ No data found');
                $failed++;
            }
        }

        $this->em->flush();

        $io->newLine();
        $io->success(sprintf(
            'Done! Enriched: %d, Skipped: %d, No data: %d',
            $updated,
            $skipped,
            $failed
        ));

        return Command::SUCCESS;
    }
}
