<?php

namespace App\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(name: 'app:send-mail', description: 'Send a test email with Symfony Mailer')]
final class SendMailCommand extends Command
{
    public function __construct(
        private readonly string $mailtrapApiToken,
        private readonly LoggerInterface $logger,
        private readonly MailerInterface $mailer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting test email command');
        $output->writeln('=' . str_repeat('=', 60));

        try {
            $this->logger->info('[CONSOLE] Starting email test command', [
                'mail_token_length' => strlen($this->mailtrapApiToken),
            ]);

            $email = (new Email())
                ->from('hello@demomailtrap.co')
                ->to('rami.benmohamed@esen.tn')
                ->subject('You are awesome!')
                ->text('Congrats for sending a test email!');

            $this->mailer->send($email);

            $output->writeln('Email sent successfully.');
            $this->logger->notice('[CONSOLE] Email sent successfully');

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $output->writeln('ERROR: ' . $exception->getMessage());
            $this->logger->error('[CONSOLE] Email test failed', [
                'exception_type' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }
}
