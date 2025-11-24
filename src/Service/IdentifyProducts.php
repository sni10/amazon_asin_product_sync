<?php

namespace App\Service;

use App\Service\Kafka\KafkaConsumer;
use App\Service\Kafka\KafkaProducer;
use Exception;
use Psr\Log\LoggerInterface;


class IdentifyProducts
{
    const PRODUCTS_PER_CHUNK = 1;
    private KafkaConsumer $kafkaConsumer;
    private KafkaProducer $kafkaProducer;
    private AmazonService $amazonService;
    private LoggerInterface $logger;

    public function __construct(
        AmazonService   $amazonService,
        LoggerInterface $logger,
        KafkaConsumer   $kafkaConsumer,
        KafkaProducer   $kafkaProducer
    )
    {
        $this->amazonService = $amazonService;
        $this->logger = $logger;
        $this->kafkaConsumer = $kafkaConsumer;
        $this->kafkaProducer = $kafkaProducer;
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function init(): self
    {
        if ($this->amazonService->hasCredentials()) {
            $this->amazonService->initializeConnection();
        } else {
            $this->logger->error('Amazon credentials not found');
            throw new Exception('Amazon credentials are empty or not set');
        }
        return $this;
    }

    /**
     * @throws Exception
     */
    public function run(): void
    {
        $this->processIncomingProducts();
    }

    public function mapPayload($data, $originalProduct): array
    {
        return [
            'upc' => $originalProduct['upc'],
            'supplier_id' => $originalProduct['supplier_id'] ?? null,
            'version' => $originalProduct['version'] ?? null,
            'version_asin' => $originalProduct['version_asin'] ?? null,
            'asin_upcs' => $data['asin_upcs'],
            'asin' => $data['asin'],
            'name' => $data['name'],
            'lang' => $data['lang'],
            'brand' => $data['brand'],
            'marketplace_id' => $data['marketplace_id'],
        ];
    }

    private function processIncomingProducts(): void
    {
        while (true) {
            $incomingProducts = $this->kafkaConsumer->consume(self::PRODUCTS_PER_CHUNK * 10);
            if (empty($incomingProducts)) {
                continue;
            }

            $productChunks = array_chunk($incomingProducts, self::PRODUCTS_PER_CHUNK);

            foreach ($productChunks as $productBatch) {
                $upcArray = array_column($productBatch, 'upc');
                $foundData = $this->amazonService->getAmazonProducts($upcArray);

                foreach ($foundData as $data) {
                    $upc = $data['upc'];
                    $originalProduct = collect($productBatch)->firstWhere('upc', $upc);
                    $payload = $this->mapPayload($data, $originalProduct);

                    $this->kafkaProducer->produce($payload);
                }
            }
        }
    }

}
