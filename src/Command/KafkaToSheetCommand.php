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
    name: 'app:asin-kafka-to-sheet',
    description: 'Consume Kafka and save to Google Sheet',
)]
class KafkaToSheetCommand extends Command
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

            $messages = $this->manageAsins->getMessages();

//            $messages = [
//                '{"version_asin": 1755868871330760, "asin_upcs": {"B09NDY5LNL": ["0888172767705", "0888172768542", "888172767705", "888172768542"], "B09NDZ7JLF": ["0888172768542", "888172768542"]}, "supplier_id": 23, "lang": "en_US", "marketplace_id": "ATVPDKIKX0DER", "upc": "0888172768542"}',
//                '{"version_asin": 1755868871330760, "asin_upcs": {"B09NDXMV6K": ["0888172767712", "0888172768559", "888172767712", "888172768559"], "B09NDXX3PJ": ["0888172768559", "888172768559"]}, "supplier_id": 23, "lang": "en_US", "marketplace_id": "ATVPDKIKX0DER", "upc": "0888172768559"}',
//                '{"version_asin": 1755868871330760, "asin_upcs": {"B0CB56PGKC": ["0762120641326", "762120641326"], "B0CH5NDPGK": ["0762120641326", "762120641326"]}, "supplier_id": 23, "lang": "en_US", "marketplace_id": "ATVPDKIKX0DER", "upc": "0762120641326"}',
//                '{"version_asin": 1755868871330760, "asin_upcs": {"B0CB557HV1": ["0766370605053", "766370605053"], "B0D7TF2VJ3": ["0766370605053", "766370605053"]}, "supplier_id": 23, "lang": "en_US", "marketplace_id": "ATVPDKIKX0DER", "upc": "0766370605053"}',
//                '{"version_asin": 1755868871330760, "asin_upcs": {"B008FCI9JE": ["0666805372959", "666805372959"], "B008FCJF5Q": ["0666805372959", "00666805372959", "666805372959"]}, "supplier_id": 23, "lang": "en_US", "marketplace_id": "ATVPDKIKX0DER", "upc": "0666805372959"}',
//                '{"version_asin": 1755868871330760, "asin_upcs": {"B00O4L7IRO": ["0766380623108", "766380623108"], "B0D7VCZW1B": ["0766380623108", "766380623108"]}, "supplier_id": 23, "lang": "en_US", "marketplace_id": "ATVPDKIKX0DER", "upc": "0766380623108"}',
//                '{"version_asin": 1755868871330760, "asin_upcs": {"B07YFD7GC8": ["5010993641888", "5010993735389", "5010993791972", "05010993641888"], "B08QCBK6P8": ["5010993641888", "5010993791972"]}, "supplier_id": 23, "lang": "en_US", "marketplace_id": "ATVPDKIKX0DER", "upc": "5010993791972"}',
//                '{"version_asin": 1755868871330760, "asin_upcs": {"B00GT2ZL52": ["0766360153779", "0766360153809", "766360153779", "766360153809"], "B077Z5M9GY": ["0766360153779", "766360153779"]}, "supplier_id": 23, "lang": "en_US", "marketplace_id": "ATVPDKIKX0DER", "upc": "0766360153779"}',
//                '{"version_asin": 1755868871330760, "asin_upcs": {"B0007NHQTS": ["0706258059257", "706258059257"], "B07BJMBXRV": ["0706258059257", "706258059257"]}, "supplier_id": 23, "lang": "en_US", "marketplace_id": "ATVPDKIKX0DER", "upc": "706258059257"}',
//                '{"version_asin": 1755868871330760, "asin_upcs": {"B003N2GO20": ["0029408402551", "0029408643213", "00029408643213", "029408402551", "029408643213"], "B00TU782RC": ["0029408643213", "029408643213"]}, "supplier_id": 23, "lang": "en_US", "marketplace_id": "ATVPDKIKX0DER", "upc": "0029408643213"}',
//                '{"version_asin": 1755868871330760, "asin_upcs": {"B07YFBBWTW": ["5010993641857", "5010993735389", "5010993791965", "05010993641857"], "B08S1C41VW": ["5010993791965"]}, "supplier_id": 23, "lang": "en_US", "marketplace_id": "ATVPDKIKX0DER", "upc": "5010993791965"}',
//                '{"version_asin": 1755868871330760, "asin_upcs": {"B0013LII7C": ["4971850769897", "04971850769897"], "B07NWGC792": ["4971850769897"]}, "supplier_id": 26, "lang": null, "marketplace_id": null, "upc": "4971850769897"}',
//                '{"version_asin": 1755868871330760, "asin_upcs": {"B000OEFRB4": ["4971850439455", "4971850755531", "04971850439455"], "B005KO7R0M": ["4971850439455"]}, "supplier_id": 26, "lang": null, "marketplace_id": null, "upc": "4971850439455"}',
//                '{"version_asin": 1755868871330760, "asin_upcs": {"B000OEH79E": ["4971850439615", "4971850763352", "04971850439615"], "B00W6QV25I": ["4936606238553", "4971850439615"]}, "supplier_id": 26, "lang": null, "marketplace_id": null, "upc": "4971850439615"}',
//                '{"version_asin": 1755868871330760, "asin_upcs": {"B0B2LFPFJ9": ["8056597642293"], "B0CMJ9172M": ["8056597642293"]}, "supplier_id": 28, "lang": "ja_JP", "marketplace_id": "A1VC38T7YXB528", "upc": "8056597642293"}'
//            ];
            $count = $this->manageAsins->processKafkaToSheet($messages);
            $io->success("Appended $count rows from Kafka to Google Sheet.");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
