<?php

namespace App\Service;

use App\Service\Kafka\GoogleKafka\GoogleKafkaConsumer;
use App\Service\Kafka\GoogleKafka\GoogleKafkaProducer;
use App\Service\GoooleHandler\GoogleSheetsHandler;
use Exception;
use Psr\Log\LoggerInterface;


class ManageAsins
{
    const PRODUCTS_PER_CHUNK = 1;
    private GoogleKafkaConsumer $kafkaConsumer;
    private GoogleKafkaProducer $kafkaProducer;
    private GoogleSheetsHandler $sheetHandler;
    private LoggerInterface $logger;
    private string $tabNameFromManage;
    private string $tabNameToManage;
    private int $consumeMessageCount;
    private string $spreadsheetId;
    private string $clearSheet;
    private string $batchRowsSheetCount;

    public function __construct(
        GoogleSheetsHandler $sheetHandler,
        LoggerInterface $logger,
        GoogleKafkaConsumer   $kafkaConsumer,
        GoogleKafkaProducer   $kafkaProducer
    )
    {
        $this->sheetHandler = $sheetHandler;
        $this->logger = $logger;
        $this->kafkaConsumer = $kafkaConsumer;
        $this->kafkaProducer = $kafkaProducer;

        $this->spreadsheetId = (string)(getenv('SPREADSHEET_ID'));
        $this->consumeMessageCount = (int)(getenv('CONSUME_MESSAGE_COUNT'));
        $this->tabNameToManage = (string)(getenv('TAB_NAME_TO_MANAGE'));
        $this->tabNameFromManage = (string)(getenv('TAB_NAME_FROM_MANAGE'));
        $this->clearSheet = (string)(getenv('CLEAR_SHEET'));
        $this->batchRowsSheetCount = (string)(getenv('BATCH_ROWS_SHEET_COUNT'));

    }

    /**
     * @throws Exception
     */
    public function processKafkaToSheet(array $messages): int
    {
        if (empty($messages)) {
            return 0;
        }

        $existing = $this->buildExistingKeysManager();
        $newRows = $this->extractNewRows($messages, $existing);

        if (empty($newRows)) {
            return 0;
        }

        $total = 0;
        foreach (array_chunk($newRows, $this->batchRowsSheetCount) as $chunk) {
            $this->sheetHandler->writeData($this->spreadsheetId, $this->tabNameToManage, $chunk);
            $total += count($chunk);
        }

        return $total;
    }

    /**
     * @throws Exception
     */
    public function processSheetToKafka(): int
    {
        $rows = $this->sheetHandler->readData(
            $this->spreadsheetId,
            $this->tabNameFromManage
        )->getRows();
        if (empty($rows)) {
            return 0;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $existingKeys = new ExistingKeysManager();
        $map = [];
        $undated = [];

        // 1. Сбор датированных и недатированных строк
        foreach ($rows as $row) {
            $supplierId = $row->getField('supplier_id');
            $upc = $row->getUpc();
            $asin = $row->getField('asin');
            $createdAt = $row->getField('created_at');
            $key = sprintf('%s_%s_%s', $supplierId, $upc, $asin);

            if (!empty($createdAt)) {
                // сохраняем датированные
                $map[$key] = [
                    'supplier_id' => $supplierId,
                    'upc'         => $upc,
                    'asin'        => $asin,
                    'created_at'  => $createdAt,
                ];
                $existingKeys->add($supplierId, $upc, $asin);
            } elseif (!isset($map[$key])) {
                // собираем недатированные уникальные
                $undated[] = ['row' => $row, 'key' => $key];
            }
        }

        if (empty($undated)) {
            return 0;
        }

        $produced = [];

        // 2. Продюсим недатированные и заменяем их в map
        foreach ($undated as $item) {
            $row = $item['row'];
            $key = $item['key'];

            $data = $row->toArray();
            unset($data['created_at']);
            $this->kafkaProducer->produce($data);

            $newRow = [
                'supplier_id' => $row->getField('supplier_id'),
                'upc'         => $row->getUpc(),
                'asin'        => $row->getField('asin'),
                'created_at'  => $now
            ];

            $map[$key] = $newRow;  // заменяем старое
            $produced[] = $newRow;
            $existingKeys->add($newRow['supplier_id'], $newRow['upc'], $newRow['asin']);
        }

        // 3. Сортировка по дате
        $finalRows = array_values($map);
        usort($finalRows, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));

        // 4. Перезаписываем таблицу (сохранен заголовок, очищена область данных)
        $this->sheetHandler->overwriteData(
            $this->spreadsheetId,
            $this->tabNameFromManage,
            $finalRows
        );

        return count($produced);
    }

    /**
     * @throws Exception
     */
    private function buildExistingKeysManager(): ExistingKeysManager
    {
        $manager = new ExistingKeysManager();
        $rows = $this->sheetHandler->readData($this->spreadsheetId, $this->tabNameToManage)->getRows();

        foreach ($rows as $row) {
            $manager->add(
                $row->getField('supplier_id'),
                $row->getUpc(),
                $row->getField('asin')
            );
        }

        return $manager;
    }

    private function extractNewRows(array $messages, ExistingKeysManager $existing): array
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $rows = [];

        foreach ($messages as $msg) {
            $supplier = $msg['supplier_id'] ?? '';
            $upc = $msg['upc'] ?? '';
            $lang = $msg['lang'] ?? '';
            $marketplace_id = $msg['marketplace_id'] ?? '';
            foreach (array_keys($msg['asin_upcs'] ?? []) as $asin) {
                if (!$existing->has($supplier, $upc, $asin)) {
                    $rows[] = [$supplier, $upc, $asin, $lang, $marketplace_id, $now];
                    $existing->add($supplier, $upc, $asin);
                }
            }
        }

        return $rows;
    }

    private function splitRows(array $rows): array
    {
        $existing = [];
        $toPush = [];

        foreach ($rows as $row) {
            if ($row->getField('created_at')) {
                $existing[] = $row->toArray();
            } else {
                $toPush[] = $row;
            }
        }

        return [$existing, $toPush];
    }

    public function getMessages(): array
    {
        return $this->kafkaConsumer->consume($this->consumeMessageCount);
    }

}
