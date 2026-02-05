<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ConcertRepository;
use App\Service\ArtistImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fetch-missing-images',
    description: 'Fetch Wikipedia images for concerts that don\'t have one yet',
)]
final class FetchMissingImagesCommand extends Command
{
    public function __construct(
        private readonly ConcertRepository $concertRepository,
        private readonly ArtistImageService $artistImageService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Refetch images even if already set');
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

            if (!$force && $concert->getArtistImage() !== null) {
                $io->text(sprintf('⏭ Skipping "%s" (already has image)', $title));
                $skipped++;
                continue;
            }

            $io->text(sprintf('🔍 Fetching image for "%s"...', $title));

            // Delete old image if force-refetching
            if ($force && $concert->getArtistImage() !== null) {
                $this->artistImageService->deleteImage($concert->getArtistImage());
            }

            $imagePath = $this->artistImageService->fetchAndStoreImage($title, $concert->getId());

            if ($imagePath !== null) {
                $concert->setArtistImage($imagePath);
                $io->text(sprintf('  ✅ Saved: %s', $imagePath));
                $updated++;
            } else {
                $io->text('  ❌ No image found');
                $concert->setArtistImage(null);
                $failed++;
            }
        }

        $this->em->flush();

        $io->newLine();
        $io->success(sprintf(
            'Done! Updated: %d, Skipped: %d, No image found: %d',
            $updated,
            $skipped,
            $failed
        ));

        return Command::SUCCESS;
    }
}
