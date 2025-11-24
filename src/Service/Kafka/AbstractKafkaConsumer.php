<?php

namespace App\Service\Kafka;

use Exception;
use Psr\Log\LoggerInterface;
use RdKafka\Conf;
use RdKafka\KafkaConsumer as RdKafkaConsumer;
use RuntimeException;

abstract class AbstractKafkaConsumer
{
    protected RdKafkaConsumer $consumer;
    protected LoggerInterface $logger;
    protected int $waitTime;
    protected int $sleepError;
    protected int $maxErrors;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

        $this->waitTime = (int)(getenv('KAFKA_WAIT_MESSAGE_TIME'));
        $this->sleepError = (int)(getenv('ON_KAFKA_ERROR_SLEEP_TIME'));
        $this->maxErrors = (int)(getenv('ON_KAFKA_MAX_ERROR_COUNT_REPEAT'));

        $this->initConsumer();
    }

    abstract protected function getGroupId(): string;
    abstract protected function getBrokerList(): string;
    abstract protected function getTopics(): array;

    protected function initConsumer(): void
    {
        $conf = new Conf();
        $conf->set('group.id', $this->getGroupId());
        $conf->set('metadata.broker.list', $this->getBrokerList());
        $conf->set('enable.auto.commit', 'true');
        $conf->set('auto.offset.reset', 'earliest');

        $this->consumer = new RdKafkaConsumer($conf);
        $this->consumer->subscribe($this->getTopics());
    }

    public function consume(int $maxMessages = 100): array
    {
        $messages = [];
        $errorCounter = 0;

        while (count($messages) < $maxMessages) {
            $message = $this->consumer->consume($this->waitTime);

            if ($message->err === RD_KAFKA_RESP_ERR_NO_ERROR) {
                $decoded = json_decode($message->payload, true);
                $messages[] = $decoded;
            } else {
                $stop = $this->handleKafkaError($message, $errorCounter);
                if ($stop || count($messages) > 0) {
                    break;
                }
            }
        }

        return $messages;
    }

    protected function handleKafkaError($message, int &$errorCounter): bool
    {
        switch ($message->err) {
            case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                $this->logger->info("Kafka: End of partition reached.");
                return true;

            case RD_KAFKA_RESP_ERR__TIMED_OUT:
                if (!$this->isKafkaAvailable()) {
                    $this->logger->error("Kafka: Timeout. Service will exit.");
                    throw new RuntimeException("Kafka timeout error.");
                }
                $this->logger->info("Kafka: No new messages.");
                return true;

            case RD_KAFKA_RESP_ERR__UNKNOWN_PARTITION:
            case RD_KAFKA_RESP_ERR__UNKNOWN_TOPIC:
            case RD_KAFKA_RESP_ERR_BROKER_NOT_AVAILABLE:
                $this->logger->error("Kafka critical error: {$message->errstr()}");
                throw new RuntimeException("Critical Kafka error: {$message->errstr()}");

            default:
                $errorCounter++;
                $this->logger->error("Kafka error: {$message->errstr()}");
                if ($errorCounter >= $this->maxErrors) {
                    throw new RuntimeException("Kafka failed {$errorCounter} times.");
                }
                sleep($this->sleepError);
                return false;
        }
    }

    protected function isKafkaAvailable(): bool
    {
        try {
            $meta = $this->consumer->getMetadata(true, null, 2000);
            return count($meta->getBrokers()) > 0;
        } catch (Exception $e) {
            $this->logger->error("Kafka metadata error: {$e->getMessage()}");
            return false;
        }
    }
}
