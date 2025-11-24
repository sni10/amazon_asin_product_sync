<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ManageAsins;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:asin-sheet-to-kafka',
    description: 'Send cleaned rows from Google Sheet to Kafka',
)]
class SheetToKafkaCommand extends Command
{
    private ManageAsins $manageAsins;

    public function __construct(ManageAsins $manageAsins)
    {
        $this->manageAsins = $manageAsins;
        set_time_limit(0);
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $count = $this->manageAsins->processSheetToKafka();
            $io->success("Pushed $count rows from Google Sheet to Kafka.");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
