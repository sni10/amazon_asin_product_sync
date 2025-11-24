<?php

namespace App\Service;


use Exception;
use Psr\Http\Message\ResponseInterface;
use SellingPartnerApi\SellingPartnerApi;
use SellingPartnerApi\Enums\Endpoint;
use SellingPartnerApi\Seller\SellerConnector;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\LimiterInterface;

class AmazonService
{
    private SellerConnector $connection;
    private bool $isUseProxy;
    private LoggerInterface $logger;

    private RateLimiterFactory $amazonApiGetProductsLimiter;

    /** @var LimiterInterface[] $limiters */
    private array $limiters = [];
    private CredentialsGenerator $credentialsGenerator;
    private \stdClass $credentials;
    private ?string $currentEndpoint = null;
    private int $maxAttempts;
    private int $sleepTime;

    public function __construct(
        bool                 $isUseProxy,
        RateLimiterFactory   $amazonApiGetProductsLimiter,
        LoggerInterface      $logger,
        CredentialsGenerator $credentialsGenerator
    )
    {
        $this->isUseProxy = $isUseProxy;
        $this->logger = $logger;
        $this->amazonApiGetProductsLimiter = $amazonApiGetProductsLimiter;
        $this->credentialsGenerator = $credentialsGenerator;
        $this->credentials = $this->credentialsGenerator->getGenerator()->current();

        $this->maxAttempts = (int)(getenv('SP_API_MAX_ERROR_COUNT_REPEAT'));
        $this->sleepTime = (int)(getenv('SP_API_ERROR_SLEEP_TIME'));
    }

    /**
     * @throws \Exception
     */
    public function initializeConnection(): void
    {
        $this->currentEndpoint = $this->credentials->endpoint;
        $this->createConnection();
    }

    private function createConnection(): void
    {
        $attempt = 0;
        while ($attempt < $this->maxAttempts) {
            try {
                if ($this->hasCredentials()) {
                    if ($this->isUseProxy) {
                        if (empty($this->credentials->proxy)) {
                            $this->logger->error('Invalid credentials proxy.');
                            throw new \Exception('Empty proxy');
                        }
                        $this->connection = SellingPartnerApi::make(
                            clientId: $this->credentials->client_id,
                            clientSecret: $this->credentials->client_secret,
                            refreshToken: $this->credentials->refresh_token,
                            endpoint: $this->getEndpointEnum($this->credentials->endpoint),
                            authenticationClient: $this->getHttpClient($this->credentials->proxy),
                        )->seller();
                    }
                }
                return;
            } catch (\Exception $e) {
                $httpStatus = $this->getHttpErrorCode($e);
                if ($httpStatus == 429) {
                    $attempt++;
                    sleep($this->sleepTime * $attempt);
                    continue;
                } else {
                    break;
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function getEndpointEnum(string $endpoint): Endpoint {
        return match ($endpoint) {
            'sellingpartnerapi-na.amazon.com' => Endpoint::NA,
            'sellingpartnerapi-eu.amazon.com' => Endpoint::EU,
            'sellingpartnerapi-fe.amazon.com' => Endpoint::FE,
            default => throw new \Exception("Invalid endpoint provided: {$endpoint}")
        };
    }

    public function hasCredentials(): bool
    {
        return !empty($this->credentials)
            && !empty($this->credentials->client_id)
            && !empty($this->credentials->client_secret)
            && !empty($this->credentials->refresh_token);
    }

    /**
     * @throws \Exception
     */
    public function reconnectIfNeeded(): void
    {
        if ($this->currentEndpoint !== $this->credentials->endpoint) {
            $this->currentEndpoint = $this->credentials->endpoint;
            $this->createConnection();
        }
    }

    /**
     * @throws \Exception
     * '714195761737'
     * '073650779374'
     * '021200475924'
     * '034689502548'
     * '023168381118'
     */
    public function getAmazonProducts(array $upcs, string $identifierType = 'UPC'): array
    {
        $result = [];

        // 1. Инициализируем пустые блоки по каждому UPC который ищем ( в примере 1 UPC и будет приходить ПО ОДНОМУ UPC )
        foreach ($upcs as $originalUpc) {
            $result[$originalUpc] = [
                'upc' => $originalUpc,
                'asin' => null,
                'asin_upcs' => null,
                'name' => null,
                'lang' => null,
                'brand' => null,
                'marketplace_id' => null,
            ];
        }

        // 2. Получаем товары все асины по UPC
        $itemsByUpc = $this->fetchItemsFromAmazon($upcs, $identifierType);

        if (count($itemsByUpc) <= 0) {
            return array_values($result);
        }

        // 3. Проходим товары все асины и сопоставляем с каждым UPC
        foreach ($itemsByUpc as $item) {
            $asin = $item['asin'] ?? null;
            if (!$asin) {
                continue;
            }

            $itemUpcs = $this->extractIdentifiersFromItem($item);

            foreach ($upcs as $originalUpc) {
                if (in_array($originalUpc, $itemUpcs, true)) {
                    // сохраняем первого подходящего asin с полями
                    if (!$result[$originalUpc]['asin']) {
                        $result[$originalUpc]['asin'] = $asin;
                        $result[$originalUpc]['name'] = $item['attributes']['item_name'][0]['value'] ?? null;
                        $result[$originalUpc]['lang'] = $item['attributes']['item_name'][0]['language_tag'] ?? null;
                        $result[$originalUpc]['brand'] = $item['attributes']['brand'][0]['value'] ?? null;
                        $result[$originalUpc]['marketplace_id'] = $item['attributes']['brand'][0]['marketplace_id'] ?? null;
                    }

                    // инициализируем пустую ячейку под asin, в будущем туда положим коды
                    $result[$originalUpc]['asin_upcs'][$asin] = [];
                }
            }
        }

        // 4. Собираем все уникальные asin из itemsByUpc
        $asinsToFetch = array_values(array_unique(
            array_filter(array_column($itemsByUpc, 'asin'))
        ));

        // 5. Повторно получаем товары по asin, чтобы получить их реальные и полные UPC/EAN/GTIN
        $itemsByAsin = $this->fetchItemsFromAmazon($asinsToFetch, 'ASIN');

        // 6. Распределяем UPC-коды по каждому asin для поля asin_upcs
        foreach ($result as &$entry) {
            foreach ($entry['asin_upcs'] as $asin => &$upcsList) {
                foreach ($itemsByAsin as $item) {
                    if (($item['asin'] ?? null) === $asin) {
                        $upcsList = $this->extractIdentifiersFromItem($item);
                    }
                }
            }

            // 7. Если asin'ов > 1 — обнуляем мета-инфу
            if (!empty($entry['asin_upcs']) && is_array($entry['asin_upcs']) && count($entry['asin_upcs']) > 1) {
                $entry['asin'] = null;
            } else {
                $entry['asin_upcs'] = null;
            }
        }

        unset($entry); // защита от yтечки
        unset($upcsList); // защита от yтечки

        return array_values($result);
    }

    /**
     * @throws \Exception
     */
    private function fetchItemsFromAmazon(array $ids, string $type): array
    {
        $generator = $this->credentialsGenerator->getGenerator();
        $items = [];
        $attempt = 0;


        while ($generator->valid() && !empty($ids)) {
            $this->credentials = $generator->current();
            $this->reconnectIfNeeded();
            $marketplaceIds = [$this->credentials->marketplace_id];
            $this->rateLimiterPause("searchCatalogItems-{$this->credentials->client_id}");

            try {
                $response = $this->connection->catalogItems()->searchCatalogItems(
                    marketplaceIds: $marketplaceIds,
                    identifiers: $ids,
                    identifiersType: $type,
                    includedData: ['identifiers', 'summaries', 'attributes']
                );

                $body = json_decode($response->body(), true);
                $items = $body['items'] ?? [];

                if (empty($items)) {
                    $generator->next();
                    $attempt = 0; // reset counter on success
                    continue;
                }

                break;
            } catch (\Exception $e) {
                $httpStatus = $this->getHttpErrorCode($e);
                if ($httpStatus == 429 && $attempt < $this->maxAttempts) {
                    $attempt++;
                    sleep($this->sleepTime*$attempt);
                    continue;
                } else {
                    $this->logger->warning("Failed to connect Amazon API after $this->maxAttempts attempts.");
                    break;
                }
            }
        }

        return $items;
    }

    private function extractIdentifiersFromItem(array $item): array
    {
        $identifiers = [];

        foreach ($item['identifiers'][0]['identifiers'] ?? [] as $ident) {
            $value = $ident['identifier'] ?? null;
            if (!empty($value)) {
                $identifiers[] = $value;
            }
        }

        return $identifiers;
    }


    private function getHttpClient($proxy): Client
    {
        return new Client([
            'proxy' => $proxy,
        ]);
    }

    private function rateLimiterPause(string $method): void
    {
        if (!isset($this->limiters[$method])) {
            $this->limiters[$method] = $this->amazonApiGetProductsLimiter->create($method);
        }
        $attempts = 0;
        do {
            if ($attempts++ > 2) {
                sleep(1);
            }
            if ($attempts > 10) {
                break;
            }
            $limit = $this->limiters[$method]->consume(1);
            $limit->wait();
        } while (!$limit->isAccepted());
    }

    private function getHttpErrorCode( Exception $e): int
    {
        $httpStatus = $e->getCode();
        if (method_exists($e, 'getResponse') && $e->getResponse()) {
            $httpStatus = $e->getResponse()->status();
        }
        $this->logger->warning("Amazon API error: HTTP {$httpStatus}, Exception code: {$e->getCode()}, Message: {$e->getMessage()}");
        return (int)$httpStatus;
    }

}