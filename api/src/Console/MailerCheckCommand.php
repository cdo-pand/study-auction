<?php

declare(strict_types=1);

namespace App\Console;

use App\Auth\Entity\User\Email;
use App\Auth\Entity\User\Token;
use App\Auth\Service\JoinConfirmationSender;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MailerCheckCommand extends Command
{
    private JoinConfirmationSender $sender;

    /**
     * Can be change type sender (for example to NewEmailConfirmTokenSender or PasswordResetTokenSender)
     * @param JoinConfirmationSender $sender
     */
    public function __construct(JoinConfirmationSender $sender)
    {
        parent::__construct();
        $this->sender = $sender;
    }

    /**
     * Name for call with console command
     */
    protected function configure(): void
    {
        $this
            ->setName('mailer:check')
            ->setDescription('Check mail sender');
    }

    /**
     * Execute command for check mail
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<comment>Sending</comment>');

        $this->sender->send(
            new Email('user@app.test'),
            new Token(Uuid::uuid4()->toString(), new DateTimeImmutable())
        );

        $output->writeln('<info>Done</info>');

        return 0;
    }
}
