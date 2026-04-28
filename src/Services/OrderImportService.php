<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Services;

use Illuminate\Support\Facades\Log;
use Zislogic\Ebay\Mip\Csv\CsvReader;
use Zislogic\Ebay\Mip\Exceptions\MipException;
use Zislogic\Ebay\Mip\Sftp\MipSftpClient;
use Zislogic\Ebay\Model\Fulfillment\Models\FulfillmentOrder;
use Zislogic\Ebay\Model\Fulfillment\Models\FulfillmentOrderLine;

final class OrderImportService
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly MipSftpClient $sftp,
        private readonly CsvReader $csvReader,
        private readonly array $config,
    ) {}

    /**
     * List available order report files on the SFTP server.
     *
     * @return array<int, array{filename: string, size: int, mtime: int}>
     *
     * @throws MipException
     */
    public function listReports(string $type = 'latest'): array
    {
        $path = $this->getReportPath($type);

        return $this->sftp->listFiles($path);
    }

    /**
     * Import the latest order report.
     *
     * @throws MipException
     */
    public function importLatest(int $credentialId, string $type = 'latest'): int
    {
        $reports = $this->listReports($type);

        if ($reports === []) {
            return 0;
        }

        // Reports are sorted newest first by MipSftpClient
        $latestFile = $reports[0]['filename'];

        return $this->importReport($latestFile, $credentialId, $type);
    }

    /**
     * Download and import a specific order report file.
     *
     * @throws MipException
     */
    public function importReport(string $filename, int $credentialId, string $type = 'latest'): int
    {
        $path = $this->getReportPath($type).'/'.$filename;

        $csvContent = $this->sftp->downloadFile($path);
        $rows = $this->csvReader->parse($csvContent);

        if ($rows === []) {
            return 0;
        }

        return $this->processRows($rows, $credentialId);
    }

    /**
     * Import orders from raw CSV content (useful for testing or manual imports).
     *
     * @throws MipException
     */
    public function importFromCsv(string $csvContent, int $credentialId): int
    {
        $rows = $this->csvReader->parse($csvContent);

        if ($rows === []) {
            return 0;
        }

        return $this->processRows($rows, $credentialId);
    }

    /**
     * Process parsed CSV rows: group by orderID, upsert orders and lines.
     *
     * @param  array<int, array<string, string>>  $rows
     */
    private function processRows(array $rows, int $credentialId): int
    {
        /** @var array<string, string> $orderColumnMap */
        $orderColumnMap = $this->config['column_map']['orders'] ?? [];

        /** @var array<string, string> $lineColumnMap */
        $lineColumnMap = $this->config['column_map']['order_lines'] ?? [];

        // The order_id CSV column name (look up from config)
        $orderIdCsvColumn = array_search('order_id', $orderColumnMap, true);

        if ($orderIdCsvColumn === false) {
            throw MipException::invalidCsv('No mapping found for order_id in column_map.orders');
        }

        // Group rows by order ID
        /** @var array<string, array<int, array<string, string>>> $grouped */
        $grouped = [];

        foreach ($rows as $row) {
            $orderId = $row[$orderIdCsvColumn] ?? '';

            if ($orderId === '') {
                continue;
            }

            $grouped[$orderId][] = $row;
        }

        $importedCount = 0;

        foreach ($grouped as $orderId => $orderRows) {
            try {
                $this->upsertOrder($orderId, $orderRows, $credentialId, $orderColumnMap, $lineColumnMap);
                $importedCount++;
            } catch (\Throwable $e) {
                Log::warning('Failed to import MIP order', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $importedCount;
    }

    /**
     * Upsert a single order and its line items.
     *
     * @param  array<int, array<string, string>>  $orderRows
     * @param  array<string, string>  $orderColumnMap
     * @param  array<string, string>  $lineColumnMap
     */
    private function upsertOrder(
        string $orderId,
        array $orderRows,
        int $credentialId,
        array $orderColumnMap,
        array $lineColumnMap,
    ): void {
        // Use first row for order-level data
        $firstRow = $orderRows[0];

        $orderData = $this->mapColumns($firstRow, $orderColumnMap);
        $orderMeta = $this->collectMeta($firstRow, $orderColumnMap, $lineColumnMap);

        $orderData['ebay_credential_id'] = $credentialId;
        $orderData['imported_at'] = now();

        if ($orderMeta !== []) {
            $orderData['meta'] = $orderMeta;
        }

        /** @var FulfillmentOrder $order */
        $order = FulfillmentOrder::query()->updateOrCreate(
            [
                'ebay_credential_id' => $credentialId,
                'order_id' => $orderId,
            ],
            $orderData,
        );

        // Upsert line items
        foreach ($orderRows as $row) {
            $lineData = $this->mapColumns($row, $lineColumnMap);
            $lineMeta = $this->collectLineMeta($row, $lineColumnMap);

            if ($lineMeta !== []) {
                $lineData['meta'] = $lineMeta;
            }

            $lineItemId = $lineData['line_item_id'] ?? '';

            if ($lineItemId === '') {
                continue;
            }

            // Preserve fulfillment fields — don't overwrite if already set
            /** @var FulfillmentOrderLine|null $existingLine */
            $existingLine = FulfillmentOrderLine::query()
                ->where('fulfillment_order_id', $order->id)
                ->where('line_item_id', $lineItemId)
                ->first();

            if ($existingLine !== null) {
                // Update only non-fulfillment fields
                $updateData = $lineData;
                unset($updateData['line_item_id']);

                // Preserve fulfillment data
                if ($existingLine->fulfillment_status !== null) {
                    unset($updateData['fulfillment_status']);
                }

                $existingLine->update($updateData);
            } else {
                $lineData['fulfillment_order_id'] = $order->id;

                FulfillmentOrderLine::query()->create($lineData);
            }
        }
    }

    /**
     * Map CSV columns to DB columns using the column map config.
     *
     * @param  array<string, string>  $row
     * @param  array<string, string>  $columnMap
     * @return array<string, string>
     */
    private function mapColumns(array $row, array $columnMap): array
    {
        $mapped = [];

        foreach ($columnMap as $csvColumn => $dbColumn) {
            if (isset($row[$csvColumn])) {
                $mapped[$dbColumn] = $row[$csvColumn];
            }
        }

        return $mapped;
    }

    /**
     * Collect unmapped order-level CSV columns into meta array.
     *
     * @param  array<string, string>  $row
     * @param  array<string, string>  $orderColumnMap
     * @param  array<string, string>  $lineColumnMap
     * @return array<string, string>
     */
    private function collectMeta(array $row, array $orderColumnMap, array $lineColumnMap): array
    {
        $mappedColumns = array_merge(
            array_keys($orderColumnMap),
            array_keys($lineColumnMap),
        );

        $meta = [];

        foreach ($row as $csvColumn => $value) {
            if (in_array($csvColumn, $mappedColumns, true)) {
                continue;
            }

            if ($value !== '') {
                $meta[$csvColumn] = $value;
            }
        }

        return $meta;
    }

    /**
     * Collect unmapped line-level CSV columns into meta array.
     *
     * @param  array<string, string>  $row
     * @param  array<string, string>  $lineColumnMap
     * @return array<string, string>
     */
    private function collectLineMeta(array $row, array $lineColumnMap): array
    {
        // Line meta only includes line-specific unmapped columns
        // We list known line-level CSV column prefixes
        $lineColumnPrefixes = [
            'lineItem', 'gift', 'linked',
        ];

        $mappedLineColumns = array_keys($lineColumnMap);

        $meta = [];

        foreach ($row as $csvColumn => $value) {
            if (in_array($csvColumn, $mappedLineColumns, true)) {
                continue;
            }

            // Check if this column is line-item level
            $isLineLevel = false;

            foreach ($lineColumnPrefixes as $prefix) {
                if (str_starts_with($csvColumn, $prefix)) {
                    $isLineLevel = true;

                    break;
                }
            }

            if ($isLineLevel && $value !== '') {
                $meta[$csvColumn] = $value;
            }
        }

        return $meta;
    }

    private function getReportPath(string $type): string
    {
        /** @var array<string, string> $paths */
        $paths = $this->config['paths'] ?? [];

        return match ($type) {
            'eod' => (string) ($paths['order_eod'] ?? '/store/order/order-latest-eod'),
            default => (string) ($paths['order_latest'] ?? '/store/order/order-latest'),
        };
    }
}
