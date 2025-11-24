<?php
// src/Service/Kafka/AbstractKafkaProducer.php
namespace App\Service\Kafka;

use Psr\Log\LoggerInterface;
use RdKafka\Conf;
use RdKafka\Producer as RdKafkaProducer;

abstract class AbstractKafkaProducer
{
    protected RdKafkaProducer $producer;
    protected LoggerInterface $logger;
    protected string $topicName;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

        $conf = new Conf();
        $conf->set('metadata.broker.list', $this->getBrokerList());
        $this->producer = new RdKafkaProducer($conf);
        $this->topicName = $this->getTopicName();
    }

    abstract protected function getBrokerList(): string;
    abstract protected function getTopicName(): string;

    public function produce(array $dataRow): void
    {
        $topic = $this->producer->newTopic($this->topicName);
        $message = json_encode($dataRow);

        if ($message === false) {
            $this->logger->error("Kafka: Failed to encode data to JSON.");
            throw new \RuntimeException("Failed to encode data to JSON.");
        }

        $topic->produce(RD_KAFKA_PARTITION_UA, 0, $message);
        $this->producer->poll(0);

        $this->waitForDelivery();
    }

    protected function waitForDelivery(): void
    {
        $retries = 5;
        $retryInterval = 500;

        while ($this->producer->getOutQLen() > 0 && $retries > 0) {
            $this->producer->poll($retryInterval);
            $retries--;

            if ($retries <= 0) {
                $this->logger->error("Kafka: Message delivery failed after multiple retries.");
                throw new \RuntimeException("Failed to deliver message to Kafka after retries.");
            }
        }

        if ($this->producer->getOutQLen() > 0) {
            $this->logger->error("Kafka: Outgoing queue is not empty.");
            throw new \RuntimeException("Kafka producer failed to clear the outgoing queue.");
        }
    }
}
