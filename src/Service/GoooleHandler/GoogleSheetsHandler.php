<?php
// src/Service/InputHandler/GoogleSheetsHandler.php
namespace App\Service\GoooleHandler;

use App\Model\DataCollection;
use App\Model\DataRow;
use Google\Service\Sheets;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Sheets\ClearValuesRequest;

class GoogleSheetsHandler extends GoogleApiHandler
{
    protected function setupService(): void
    {
        $this->client->addScope(Sheets::SPREADSHEETS);
        $this->service = new Sheets($this->client);
    }

    /**
     * @throws \Exception
     */
    public function readData(string $source, ?string $range = null): DataCollection
    {
        $this->authenticateClient();
        $collection = new DataCollection();

        try {
            $response = $this->service->spreadsheets_values->get($source, $range);
            $values = $response->getValues();

            if (empty($values) || count($values) < 2) {
                $this->logger->warning("GoogleSheets: Пустая таблица или отсутствует заголовок: $source");
                return $collection;
            }

            $header = array_map('trim', array_shift($values));
            foreach ($values as $row) {
                $row = array_map('trim', array_pad($row, count($header), null));
                $rowData = @array_combine($header, $row);
                if ($this->isValidRow($rowData)) {
                    $collection->add(new DataRow($rowData));
                } else {
                    $this->logger->warning("GoogleSheets: Некорректная строка пропущена: " . json_encode($rowData));
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error("GoogleSheets: Ошибка при чтении '$source': " . $e->getMessage());
        }

        return $collection;
    }

    /**
     * @throws \Exception
     */
    public function writeData(string $spreadsheetId, string $range, array $rows): void
    {
        $this->authenticateClient();

        $values = array_filter(array_map(function ($row) {
            $data = $row instanceof DataRow ? $row->toArray() : $row;
            return array_values($data);
        }, $rows));


        $body = new Sheets\ValueRange(['values' => $values]);

        try {
            $this->service->spreadsheets_values->append(
                $spreadsheetId,
                $range,
                $body,
                ['valueInputOption' => 'RAW', 'insertDataOption' => 'INSERT_ROWS']
            );
        } catch (\Throwable $e) {
            $this->logger->error("GoogleSheets: Ошибка при записи в '$spreadsheetId': " . $e->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    public function overwriteData(string $spreadsheetId, string $sheetNameRange, array $rows): void
    {
        $this->authenticateClient();

        try {
            // 1) Отделяем имя листа от суффикса диапазона
            [$sheetName] = explode('!', $sheetNameRange, 2);

            // 2) Читаем заголовок (1-я строка)
            $headerRange = "{$sheetName}!1:1";
            $response    = $this->service->spreadsheets_values->get($spreadsheetId, $headerRange);
            $header      = $response->getValues()[0] ?? [];
            if (empty($header)) {
                $this->logger->error("GoogleSheets: Заголовок не найден в '{$sheetName}'");
                return;
            }

            // 3) Очищаем все строки под заголовком (A2:Z)
            $this->service->spreadsheets_values->clear(
                $spreadsheetId,
                "{$sheetName}!A2:Z",
                new ClearValuesRequest()
            );

            // 4) Формируем массив values: сначала заголовок, потом данные
            $values = [];
            $values[] = $header;
            foreach ($rows as $row) {
                $assoc = is_array($row) ? $row : $row->toArray();
                $line = [];
                foreach ($header as $col) {
                    // array_key_exists не выбросит warning, если ключа нет
                    $line[] = array_key_exists($col, $assoc) ? $assoc[$col] : '';
                }
                $values[] = $line;
            }

            // 5) Перезаписываем всю область с A1
            $body = new \Google\Service\Sheets\ValueRange(['values' => $values]);
            $this->service->spreadsheets_values->update(
                $spreadsheetId,
                "{$sheetName}!A1",
                $body,
                ['valueInputOption' => 'RAW']
            );
        } catch (\Throwable $e) {
            $this->logger->error("GoogleSheets: overwriteData ошибка — " . $e->getMessage());
        }
    }


    private function isValidRow(array $rowData, array $requiredFields = ['supplier_id', 'upc', 'asin']): bool
    {
        foreach ($requiredFields as $field) {
            if (!isset($rowData[$field]) || $rowData[$field] === null || $rowData[$field] === '') {
                return false;
            }
        }
        return true;
    }


}
