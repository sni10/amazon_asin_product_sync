<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\DataCollection;
use App\Model\DataRow;
use PHPUnit\Framework\TestCase;

class DataCollectionTest extends TestCase
{
    public function testEmptyCollectionHasZeroCount(): void
    {
        $collection = new DataCollection();

        $this->assertSame(0, $collection->count());
        $this->assertSame([], $collection->getRows());
    }

    public function testAddRow(): void
    {
        $collection = new DataCollection();
        $row = new DataRow(['upc' => '012345678901']);

        $collection->add($row);

        $this->assertSame(1, $collection->count());
        $this->assertSame([$row], $collection->getRows());
    }

    public function testAddMultipleRows(): void
    {
        $collection = new DataCollection();
        $row1 = new DataRow(['upc' => '012345678901']);
        $row2 = new DataRow(['upc' => '012345678902']);
        $row3 = new DataRow(['upc' => '012345678903']);

        $collection->add($row1);
        $collection->add($row2);
        $collection->addNoIndex($row3);

        $this->assertSame(3, $collection->count());
    }

    public function testGetRowsReturnsAllAddedRows(): void
    {
        $collection = new DataCollection();
        $row1 = new DataRow(['upc' => '111']);
        $row2 = new DataRow(['upc' => '222']);

        $collection->add($row1);
        $collection->add($row2);

        $rows = $collection->getRows();

        $this->assertCount(2, $rows);
        $this->assertSame('111', $rows[0]->getUpc());
        $this->assertSame('222', $rows[1]->getUpc());
    }
}
