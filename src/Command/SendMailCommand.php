<?php
# src/Command/SendMailCommand.php
# php bin/console app:send-mail

namespace App\Command;

use Mailtrap\Helper\ResponseHelper;
use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;

#[AsCommand(name: 'app:send-mail', description: 'Send a test email via Mailtrap with debugging')]
final class SendMailCommand extends Command
{
    private string $mailtrapToken;
    private LoggerInterface $logger;

    public function __construct(string $mailtrapApiToken, LoggerInterface $logger)
    {
        parent::__construct();
        $this->mailtrapToken = $mailtrapApiToken;
        $this->logger = $logger;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🚀 Starting Mailtrap Email Test');
        $output->writeln('=' . str_repeat('=', 60));
        
        try {
            $this->logger->info('📧 [CONSOLE] Starting email test command');
            $this->logger->info('API Token Length: ' . strlen($this->mailtrapToken));
            
            $email = (new MailtrapEmail())
                ->from(new Address('hello@demomailtrap.co', 'Mailtrap Test'))
                ->to(new Address('rami.benmohamed@esen.tn'))
                ->subject('You are awesome!')
                ->category('Integration Test')
                ->text('Congrats for sending test email with Mailtrap!')
            ;

            $output->writeln('📝 Email Message Created');
            $this->logger->debug('Email created', [
                'from' => 'hello@demomailtrap.co',
                'to' => 'rami.benmohamed@esen.tn',
                'subject' => 'You are awesome!',
            ]);

            $output->writeln('🔗 Connecting to Mailtrap API...');
            $this->logger->info('🔗 [CONSOLE] Connecting to Mailtrap API');
            
            $response = MailtrapClient::initSendingEmails(
                apiKey: $this->mailtrapToken
            )->send($email);

            $result = ResponseHelper::toArray($response);
            $statusCode = $response->getStatusCode() ?? 'Unknown';

            $output->writeln('');
            $output->writeln('✅ Response Received!');
            $output->writeln('Status Code: ' . $statusCode);
            $output->writeln('');
            $output->writeln('📊 Full Response:');
            $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            
            $this->logger->notice('✅ [CONSOLE] Email sent successfully', [
                'status_code' => $statusCode,
                'response' => $result,
            ]);
            
            $output->writeln('');
            $output->writeln('=' . str_repeat('=', 60));
            $output->writeln('✨ Test completed successfully!');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('');
            $output->writeln('❌ ERROR: ' . $e->getMessage());
            $output->writeln('Exception Code: ' . $e->getCode());
            $output->writeln('File: ' . $e->getFile() . ':' . $e->getLine());
            $output->writeln('');
            $output->writeln($e->getTraceAsString());
            
            $this->logger->error('❌ [CONSOLE] Email test failed', [
                'exception_type' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}