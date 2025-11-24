<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\IdentifyProducts;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:id-cross-var',
    description: 'Identify Cross Variations',
)]
//php bin/console app:id-cross-var
class IdentifyCrossVariationsCommand extends Command
{
    private IdentifyProducts $identifyProducts;

    public function __construct(IdentifyProducts $identifyProducts) {
        $this->identifyProducts = $identifyProducts;
        set_time_limit(0);
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('id-cross-var');
    }

    /**
     * @throws Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->identifyProducts
                ->init()
                ->run();
            $io->success('Kafka consumer finished processing.');
            $io->success('IdentifyCrossVariationsCommand is finished');
            return Command::SUCCESS;
        } catch (\RuntimeException $e) {
            $io->error('Critical error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('Unexpected error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}