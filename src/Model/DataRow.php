<?php

namespace App\Model;

class DataRow
{
    private array $fields = [];

    public function __construct(array $data = [], int $counter = 0)
    {
        $this->fields = $data;
    }

    public function setField(string $name, $value): void
    {
        $this->fields[$name] = $value;
    }

    public function getField(string $name)
    {
        return $this->fields[$name] ?? null;
    }

    public function hasField(string $name): bool
    {
        return array_key_exists($name, $this->fields);
    }

    public function toArray(): array
    {
        return $this->fields;
    }

    public function toJson(): string
    {
        return json_encode($this->fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }


    public function getUpc(): ?string
    {
        return $this->getField('upc');
    }

    public function setUpc(string $upc): void
    {
        $this->setField('upc', $upc);
    }

    public function getFields(): array
    {
        return $this->fields;
    }
}
