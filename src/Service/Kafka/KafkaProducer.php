<?php

namespace App\Service\Kafka;

class KafkaProducer extends AbstractKafkaProducer
{
    protected function getBrokerList(): string
    {
        return getenv('KAFKA_EDI_SERVER_HOST') . ':' . getenv('KAFKA_EDI_SERVER_PORT');
    }

    protected function getTopicName(): string
    {
        return (string)getenv('UPC_IN_OUTPUT_TOPIC');
    }
}
