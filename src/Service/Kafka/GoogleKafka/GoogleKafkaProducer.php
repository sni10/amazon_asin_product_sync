<?php

namespace App\Service\Kafka\GoogleKafka;

use App\Service\Kafka\AbstractKafkaProducer;

class GoogleKafkaProducer extends AbstractKafkaProducer
{
    protected function getBrokerList(): string
    {
        return getenv('KAFKA_EDI_SERVER_HOST') . ':' . getenv('KAFKA_EDI_SERVER_PORT');
    }

    protected function getTopicName(): string
    {
        return (string)(getenv('GOOGLE_TO_ASINS_TOPIC'));
    }
}
