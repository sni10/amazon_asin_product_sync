<?php

namespace App\Service\Kafka\GoogleKafka;

use App\Service\Kafka\AbstractKafkaConsumer;

class GoogleKafkaConsumer extends AbstractKafkaConsumer
{
    protected function getGroupId(): string
    {
        return (string)(getenv('ASINS_TO_GOOGLE_INPUT_CONSUMER_GROUP'));
    }

    protected function getBrokerList(): string
    {
        return getenv('KAFKA_EDI_SERVER_HOST') . ':' . getenv('KAFKA_EDI_SERVER_PORT');
    }

    protected function getTopics(): array
    {
        return [(string)(getenv('ASINS_TO_GOOGLE_TOPIC'))];
    }
}
