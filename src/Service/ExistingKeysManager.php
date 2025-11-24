<?php
// src/Service/ExistingKeysManager.php
namespace App\Service;

class ExistingKeysManager
{
    private array $existing = [];

    public function has(string|int|null $supplierId, ?string $upc, ?string $asin): bool
    {
        return isset($this->existing[$this->buildKey($supplierId, $upc, $asin)]);
    }

    public function add(string|int|null $supplierId, ?string $upc, ?string $asin): void
    {
        $this->existing[$this->buildKey($supplierId, $upc, $asin)] = true;
    }

    private function buildKey(string|int|null $supplierId, ?string $upc, ?string $asin): string
    {
        return sprintf('%s_%s_%s', $supplierId ?? '', $upc ?? '', $asin ?? '');
    }
}
