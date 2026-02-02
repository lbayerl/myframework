<?php

declare(strict_types=1);

namespace MyFramework\Core\Command;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'myframework:vapid:generate',
    description: 'Erzeugt VAPID Keys (Public/Private) für Web Push und gibt passende ENV-Zeilen aus.'
)]
final class GenerateVapidKeysCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'subject',
                null,
                InputOption::VALUE_OPTIONAL,
                'VAPID subject, z.B. mailto:you@example.com oder https://example.com',
                'mailto:you@example.com'
            )
            ->addOption(
                'format',
                null,
                InputOption::VALUE_OPTIONAL,
                'Ausgabeformat: env (Default) oder json',
                'env'
            )
            ->addOption(
                'copy',
                null,
                InputOption::VALUE_NONE,
                'Gibt nur die Werte aus (praktisch zum Copy/Paste)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $subject = (string) $input->getOption('subject');
        $format = (string) $input->getOption('format');
        $copyOnly = (bool) $input->getOption('copy');

        $keys = VAPID::createVapidKeys();
        $publicKey = $keys['publicKey'] ?? null;
        $privateKey = $keys['privateKey'] ?? null;

        if (!\is_string($publicKey) || !\is_string($privateKey)) {
            $output->writeln('<error>Konnte VAPID Keys nicht erzeugen.</error>');
            return Command::FAILURE;
        }

        if ($copyOnly) {
            $output->writeln($publicKey);
            $output->writeln($privateKey);
            $output->writeln($subject);
            return Command::SUCCESS;
        }

        if ($format === 'json') {
            $output->writeln(json_encode([
                'VAPID_PUBLIC_KEY' => $publicKey,
                'VAPID_PRIVATE_KEY' => $privateKey,
                'VAPID_SUBJECT' => $subject,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        if ($format !== 'env') {
            $output->writeln('<error>Ungültiges --format. Erlaubt: env, json</error>');
            return Command::INVALID;
        }

        $output->writeln('# VAPID (Web Push)');
        $output->writeln('VAPID_PUBLIC_KEY=' . $publicKey);
        $output->writeln('VAPID_PRIVATE_KEY=' . $privateKey);
        $output->writeln('VAPID_SUBJECT=' . $subject);

        return Command::SUCCESS;
    }
}
