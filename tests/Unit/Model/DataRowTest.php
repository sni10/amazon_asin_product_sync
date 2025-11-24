<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\DataRow;
use PHPUnit\Framework\TestCase;

class DataRowTest extends TestCase
{
    public function testConstructorWithData(): void
    {
        $data = ['upc' => '012345678901', 'asin' => 'B00TEST123'];
        $row = new DataRow($data);

        $this->assertSame('012345678901', $row->getField('upc'));
        $this->assertSame('B00TEST123', $row->getField('asin'));
    }

    public function testSetAndGetField(): void
    {
        $row = new DataRow();

        $row->setField('sku', 'SKU-001');

        $this->assertSame('SKU-001', $row->getField('sku'));
    }

    public function testGetFieldReturnsNullForMissingKey(): void
    {
        $row = new DataRow();

        $this->assertNull($row->getField('nonexistent'));
    }

    public function testHasField(): void
    {
        $row = new DataRow(['upc' => '012345678901']);

        $this->assertTrue($row->hasField('upc'));
        $this->assertFalse($row->hasField('asin'));
    }

    public function testToArray(): void
    {
        $data = ['upc' => '012345678901', 'asin' => 'B00TEST123'];
        $row = new DataRow($data);

        $this->assertSame($data, $row->toArray());
    }

    public function testToJson(): void
    {
        $row = new DataRow(['upc' => '012345678901']);

        $json = $row->toJson();

        $this->assertJson($json);
        $this->assertSame('{"upc":"012345678901"}', $json);
    }

    public function testGetAndSetUpc(): void
    {
        $row = new DataRow();

        $row->setUpc('012345678901');

        $this->assertSame('012345678901', $row->getUpc());
    }

    public function testGetFields(): void
    {
        $data = ['upc' => '012345678901', 'asin' => 'B00TEST123'];
        $row = new DataRow($data);

        $this->assertSame($data, $row->getFields());
    }
}
