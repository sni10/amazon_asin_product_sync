<?php
// src/Service/InputHandler/GoogleHandlerInterface.php
namespace App\Service\GoooleHandler;

use App\Model\DataCollection;
use App\Model\DataRow;

interface GoogleHandlerInterface
{
    public function readData(string $source, ?string $range = null): DataCollection;

    /**
     * @param string $spreadsheetId
     * @param string $range
     * @param array<DataRow|array> $rows
     */
    public function writeData(string $spreadsheetId, string $range, array $rows): void;

}
