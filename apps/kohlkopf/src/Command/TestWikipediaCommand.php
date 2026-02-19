<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ConcertRepository;
use App\Service\MusicBrainzClient;
use App\Service\WikipediaClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-enrichment',
    description: 'Dry-run: test MusicBrainz + Wikipedia enrichment pipeline (no persistence)',
)]
final class TestWikipediaCommand extends Command
{
    public function __construct(
        private readonly MusicBrainzClient $musicBrainzClient,
        private readonly WikipediaClient $wikipediaClient,
        private readonly ConcertRepository $concertRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('artist', InputArgument::OPTIONAL, 'Single artist name to test')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Test all concerts from the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $artist = $input->getArgument('artist');
        $all = $input->getOption('all');

        if ($all) {
            return $this->testAllConcerts($io);
        }

        if ($artist === null) {
            $io->error('Provide an artist name or use --all to test all concerts.');
            return Command::FAILURE;
        }

        return $this->testSingleArtist($io, $artist);
    }

    private function testSingleArtist(SymfonyStyle $io, string $artist): int
    {
        $io->title('Testing enrichment pipeline for: ' . $artist);
        $result = $this->runPipeline($artist);
        $this->printDetailedResult($io, $artist, $result);

        return Command::SUCCESS;
    }

    private function testAllConcerts(SymfonyStyle $io): int
    {
        $concertData = $this->concertRepository->findAll();
        $total = count($concertData);
        $io->title(sprintf('Dry-run enrichment for %d concerts', $total));
        $io->warning('This is READ-ONLY — nothing is persisted. Respecting MusicBrainz rate limit (1 req/sec).');
        $io->newLine();

        // Deduplicate: same artist name doesn't need two MB lookups
        $seen = [];
        $results = [];

        foreach ($concertData as $i => $item) {
            $concert = $item['concert'];
            $title = $concert->getTitle();
            $key = mb_strtolower(trim($title));

            $io->text(sprintf('[%d/%d] "%s"…', $i + 1, $total, $title));

            if (isset($seen[$key])) {
                $io->text('  ↳ (duplicate name, reusing previous result)');
                $results[] = ['title' => $title, ...$seen[$key]];
                continue;
            }

            $result = $this->runPipeline($title);
            $seen[$key] = $result;
            $results[] = ['title' => $title, ...$result];

            // Inline status
            $status = sprintf(
                '  ↳ MB: %s | Wiki: %s | Image: %s | Genres: %s',
                $result['mbOk'] ? '✅ ' . ($result['mbType'] ?? '') : '❌',
                $result['wikiOk'] ? '✅' : '❌',
                $result['hasImage'] ? '✅' : '❌',
                $result['genres'] !== '' ? $result['genres'] : '—',
            );
            $io->text($status);

            if ($result['wikiTitle'] !== null && mb_strtolower($result['wikiTitle']) !== $key) {
                $io->text('  ⚠  Wikipedia title differs: "' . $result['wikiTitle'] . '"');
            }

            $io->newLine();
        }

        // Summary table
        $io->section('Summary');
        $tableRows = [];
        foreach ($results as $r) {
            $tableRows[] = [
                $r['title'],
                $r['mbOk'] ? '✅ ' . ($r['mbName'] ?? '') : '❌',
                $r['mbType'] ?? '—',
                $r['genres'] !== '' ? $r['genres'] : '—',
                $r['mbWikipediaUrl'] !== null ? '✅' : '—',
                $r['wikiOk'] ? '✅' : '❌',
                $r['hasImage'] ? '✅' : '❌',
                $r['wikiSource'] ?? '—',
            ];
        }

        $io->table(
            ['Concert', 'MusicBrainz', 'Type', 'Genres', 'MB→Wiki', 'Wiki', 'Image', 'Wiki Source'],
            $tableRows,
        );

        // Stats
        $mbHits = count(array_filter($results, fn($r) => $r['mbOk']));
        $wikiHits = count(array_filter($results, fn($r) => $r['wikiOk']));
        $imageHits = count(array_filter($results, fn($r) => $r['hasImage']));

        $io->success(sprintf(
            'Results: %d/%d MusicBrainz | %d/%d Wikipedia | %d/%d Images',
            $mbHits, $total, $wikiHits, $total, $imageHits, $total,
        ));

        return Command::SUCCESS;
    }

    /**
     * Run the full lookup pipeline for one artist name. Returns a flat result array.
     *
     * @return array{mbOk: bool, mbName: string|null, mbid: string|null, mbType: string|null, genres: string, wikipediaUrl: string|null, wikiOk: bool, wikiTitle: string|null, wikiDescription: string|null, hasImage: bool, wikiSource: string|null}
     */
    private function runPipeline(string $artistName): array
    {
        $result = [
            'mbOk' => false,
            'mbName' => null,
            'mbid' => null,
            'mbType' => null,
            'genres' => '',
            'mbWikipediaUrl' => null,  // URL from MusicBrainz (may be null)
            'wikipediaUrl' => null,     // Final URL (from MB or fallback)
            'wikiOk' => false,
            'wikiTitle' => null,
            'wikiDescription' => null,
            'hasImage' => false,
            'wikiSource' => null,
        ];

        // Step 1: MusicBrainz
        try {
            $mbData = $this->musicBrainzClient->searchArtist($artistName);
        } catch (\Throwable $e) {
            $mbData = null;
        }

        if ($mbData !== null) {
            $result['mbOk'] = true;
            $result['mbName'] = $mbData['name'];
            $result['mbid'] = $mbData['mbid'];
            $result['mbType'] = $mbData['type'];
            $result['genres'] = implode(', ', $mbData['genres']);
            $result['mbWikipediaUrl'] = $mbData['wikipediaUrl'];
            $result['wikipediaUrl'] = $mbData['wikipediaUrl'];
        }

        // Step 2: Wikipedia (prefer MB's URL, fallback to search)
        $wikiData = null;

        if ($result['mbWikipediaUrl'] !== null) {
            try {
                $wikiData = $this->wikipediaClient->enrichByUrl($result['mbWikipediaUrl']);
                if ($wikiData !== null) {
                    $result['wikiSource'] = 'via MusicBrainz URL';
                }
            } catch (\Throwable) {
                $wikiData = null;
            }
        }

        if ($wikiData === null) {
            try {
                $wikiData = $this->wikipediaClient->enrichArtist($artistName);
                if ($wikiData !== null) {
                    $result['wikiSource'] = 'search fallback';
                    $result['wikipediaUrl'] = $wikiData['wikipedia_url'] ?? null;
                }
            } catch (\Throwable) {
                $wikiData = null;
            }
        }

        if ($wikiData !== null) {
            $result['wikiOk'] = true;
            $result['wikiTitle'] = $wikiData['title'] ?? null;
            $result['wikiDescription'] = $wikiData['description'] ?? null;
            $result['hasImage'] = ($wikiData['image']['thumbnail'] ?? null) !== null;
        }

        return $result;
    }

    private function printDetailedResult(SymfonyStyle $io, string $artist, array $result): void
    {
        // MusicBrainz
        $io->section('Step 1: MusicBrainz');
        if (!$result['mbOk']) {
            $io->warning('No MusicBrainz results — will fall back to Wikipedia search');
        } else {
            $io->success('Found MusicBrainz artist:');
            $io->table(['Key', 'Value'], [
                ['name', $result['mbName']],
                ['mbid', $result['mbid']],
                ['type', $result['mbType'] ?? 'null'],
                ['genres', $result['genres'] !== '' ? $result['genres'] : '(none)'],
                ['wikipediaUrl (from MB)', $result['mbWikipediaUrl'] ?? '(none — MB has no wiki link)'],
            ]);
        }

        // Wikipedia
        $io->section('Step 2: Wikipedia');
        if (!$result['wikiOk']) {
            $io->error('Wikipedia returned no data');
        } else {
            $io->success(sprintf('Wikipedia data (source: %s):', $result['wikiSource']));
            $io->table(['Key', 'Value'], [
                ['title', $result['wikiTitle'] ?? 'null'],
                ['description', $result['wikiDescription'] ?? 'null'],
                ['image', $result['hasImage'] ? '✅ available' : '❌ none'],
                ['url (final)', $result['wikipediaUrl'] ?? 'null'],
            ]);
        }

        // Summary
        $io->section('Summary');
        $io->text(sprintf(
            'MusicBrainz: %s | Wikipedia: %s | Image: %s | Genres: %s',
            $result['mbOk'] ? '✅ MBID=' . $result['mbid'] : '❌',
            $result['wikiOk'] ? '✅' : '❌',
            $result['hasImage'] ? '✅' : '❌',
            $result['genres'] !== '' ? $result['genres'] : '(none)',
        ));
    }
}
