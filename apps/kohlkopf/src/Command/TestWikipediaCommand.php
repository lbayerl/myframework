<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ArtistImageService;
use App\Service\WikipediaClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-wikipedia',
    description: 'Test Wikipedia API and image download for an artist',
)]
final class TestWikipediaCommand extends Command
{
    public function __construct(
        private readonly WikipediaClient $wikipediaClient,
        private readonly ArtistImageService $artistImageService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('artist', InputArgument::REQUIRED, 'Artist name to search for');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $artist = $input->getArgument('artist');

        $io->title('Testing Wikipedia API for: ' . $artist);

        // Step 1: Test WikipediaClient
        $io->section('Step 1: WikipediaClient->enrichArtist()');
        $data = $this->wikipediaClient->enrichArtist($artist);

        if ($data === null) {
            $io->error('WikipediaClient returned null - no data found');
            return Command::FAILURE;
        }

        $io->success('Found Wikipedia data:');
        $io->table(
            ['Key', 'Value'],
            [
                ['title', $data['title'] ?? 'null'],
                ['type', $data['type'] ?? 'null'],
                ['description', $data['description'] ?? 'null'],
                ['thumbnail', $data['image']['thumbnail'] ?? 'null'],
                ['original', $data['image']['original'] ?? 'null'],
                ['wikipedia_url', $data['wikipedia_url'] ?? 'null'],
            ]
        );

        // Step 2: Test image download
        $io->section('Step 2: ArtistImageService->fetchAndStoreImage()');
        $imagePath = $this->artistImageService->fetchAndStoreImage($artist, 'test-' . time());

        if ($imagePath === null) {
            $io->warning('No image was downloaded');
        } else {
            $io->success('Image saved to: ' . $imagePath);
        }

        return Command::SUCCESS;
    }
}
