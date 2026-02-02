<?php

declare(strict_types=1);

namespace MyFramework\Core\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'myframework:mailer:test',
    description: 'Send a test email to verify mailer configuration'
)]
final class TestMailerCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $fromEmail,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Recipient email address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = $input->getArgument('to');

        $io->info(sprintf('Sending test email from %s to %s...', $this->fromEmail, $to));

        try {
            $email = (new Email())
                ->from('ludwig@bayerlcloud.de')
                ->sender('ludwig@bayerlcloud.de')
                ->to($to)
                ->subject('Email from MyFramework')
                ->text('This is a test email to verify that your mailer configuration is working correctly.')
                ->html('<p>This is a <strong>test email</strong> to verify that your mailer configuration is working correctly.</p>');

            $this->mailer->send($email);

            $io->success('Test email sent successfully!');
            $io->note('Check your inbox (and spam folder) for the email.');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Failed to send test email: ' . $e->getMessage());
            $io->writeln('Full error: ' . $e::class);
            
            return Command::FAILURE;
        }
    }
}
