<?php

namespace App\Service\Kafka;

class KafkaConsumer extends AbstractKafkaConsumer
{
    protected function getGroupId(): string
    {
        return (string)getenv('UPC_CONSUMER_GROUP');
    }

    protected function getBrokerList(): string
    {
        return getenv('KAFKA_EDI_SERVER_HOST') . ':' . getenv('KAFKA_EDI_SERVER_PORT');
    }

    protected function getTopics(): array
    {
        return [(string)getenv('UPC_IN_INPUT_TOPIC')];
    }
}
