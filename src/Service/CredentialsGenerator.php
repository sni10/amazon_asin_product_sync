<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class CredentialsGenerator
{
    private ?string $mainClientId = null;
    private array $credentials = [];
    private array $orderedCredentials = [];

    private LoggerInterface $logger;
    private array $mainList = [
        'ATVPDKIKX0DER',
        'A2EUQ1WTGCTBG2',
        'A1AM78C64UM0Y8',
        'A39IBJ37TRP1C6',
        'A2VIGQ35RCS4UG',
//        'A17E79C6D8DWNP',
        'A2Q3Y263D00KWC',
        'A19VAU5U5O7RUS',
        'A1PA6795UKMFR9',
        'A1F83G8C2ARO7P',
        'A1RKKUPIHCS9HS',
        'A13V1IB3VIYZZH',
        'APJ6JRA9NG5V4',
        'A2NODRKZP88ZB9',
        'A1805IZSGTT6HS',
        'A33AVAJ2PDY3EV',
        'A1C3SOZRARQ6R3',
        'AMEN7PMS3EDWL',
        'A1VC38T7YXB528',
    ];

    /**
     * @throws \Exception
     */
    public function __construct(LoggerInterface $logger, string $credentialsFilePath, string $proxyFilePath)
    {
        $this->logger = $logger;
        $this->credentials = json_decode(file_get_contents($credentialsFilePath), true)['credentials'] ?? [];
        $proxyConfig = json_decode(file_get_contents($proxyFilePath), true)['proxy'] ?? [];

        if (empty($this->credentials)) {
            $e = "Empty credentials file or file unavailable";
            $this->logger->error($e);
            throw new \Exception($e);
        }

        if (empty($proxyConfig)) {
            $e = "Empty proxies file or file unavailable";
            $this->logger->error($e);
            throw new \Exception($e);
        }

        $proxiesBySeller = array_map(function ($proxyList) {
            return $proxyList[0] ?? null;
        }, $proxyConfig);

        foreach ($this->credentials as &$cred) {
            $sellerId = $cred['seller_id'];
            $cred['proxy'] = $proxiesBySeller[$sellerId] ?? null;
        }

        foreach ($this->mainList as $marketplaceId) {
            foreach ($this->credentials as $credentials) {
                if ($credentials['marketplace_id'] === $marketplaceId) {
                    $this->orderedCredentials[$marketplaceId] = $credentials;
                    break;
                }
            }
        }

        if (!empty($this->credentials[0]['client_id'])) {
            $this->mainClientId = $this->credentials[0]['client_id'];
        }
    }

    public function getGenerator(): \Generator
    {
        foreach ($this->mainList as $marketplaceId) {
            if (!isset($this->orderedCredentials[$marketplaceId])) {
                continue;
            }

            $cred = $this->orderedCredentials[$marketplaceId];

//            if ($this->mainClientId !== null && $cred['client_id'] !== $this->mainClientId) {
//                continue;
//            }

            $credentialObject = new \stdClass();
            foreach ($cred as $key => $value) {
                $credentialObject->$key = $value;
            }
            yield $marketplaceId => $credentialObject;
        }
    }
}
