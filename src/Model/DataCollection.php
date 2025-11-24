<?php
// src/Model/DataCollection.php
namespace App\Model;

class DataCollection
{
    /**
     * @var DataRow[]
     */
    private array $dataRows;

    protected array $rules;


    public function __construct(array $rules = [])
    {
        $this->dataRows = [];
    }

    public function add(DataRow $dataRow): void
    {
        $this->dataRows[] = $dataRow;
    }

    public function addNoIndex(DataRow $dataRow): void
    {
        $this->dataRows[] = $dataRow;
    }

    /**
     * @return DataRow[]
     */
    public function getRows(): array
    {
        return $this->dataRows;
    }

    public function count(): int
    {
        return count($this->dataRows);
    }

}
