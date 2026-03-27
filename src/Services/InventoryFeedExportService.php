<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Services;

use Illuminate\Database\Eloquent\Collection;
use Zislogic\Ebay\Mip\Csv\CsvWriter;
use Zislogic\Ebay\Mip\Exceptions\MipException;
use Zislogic\Ebay\Mip\Models\MipFeed;
use Zislogic\Ebay\Mip\Sftp\MipSftpClient;
use Zislogic\Ebay\Model\Inventory\Models\InventoryItem;

final class InventoryFeedExportService
{
    private const HEADERS = [
        'SKU',
        'Channel ID',
        'Total Ship To Home Quantity',
        'List Price',
    ];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly MipSftpClient $sftp,
        private readonly CsvWriter $csvWriter,
        private readonly array $config,
    ) {}

    /**
     * Export all inventory items for a credential as an inventory feed.
     *
     * @return array{filename: string, count: int}
     *
     * @throws MipException
     */
    public function export(int $credentialId): array
    {
        /** @var Collection<int, InventoryItem> $items */
        $items = InventoryItem::query()
            ->where('ebay_credential_id', $credentialId)
            ->with(['offers'])
            ->get();

        if ($items->isEmpty()) {
            return ['filename' => '', 'count' => 0];
        }

        return $this->buildAndUpload($credentialId, $items);
    }

    /**
     * Export specific inventory items.
     *
     * @param Collection<int, InventoryItem> $items
     * @return array{filename: string, count: int}
     *
     * @throws MipException
     */
    public function exportItems(Collection $items): array
    {
        if ($items->isEmpty()) {
            return ['filename' => '', 'count' => 0];
        }

        $credentialId = $items->first()->ebay_credential_id;

        return $this->buildAndUpload($credentialId, $items);
    }

    /**
     * @param Collection<int, InventoryItem> $items
     * @return array{filename: string, count: int}
     *
     * @throws MipException
     */
    private function buildAndUpload(int $credentialId, Collection $items): array
    {
        $rows = [];

        foreach ($items as $item) {
            $offer = $item->offers->first();

            if ($offer === null) {
                continue;
            }

            $rows[] = [
                $item->sku,
                $offer->marketplace_code ?? '',
                (string) $item->quantity,
                $offer->price ?? '',
            ];
        }

        $csvContent = $this->csvWriter->generate(self::HEADERS, $rows);
        $filename = 'product-inventory_' . date('Ymd_His') . '.csv';

        /** @var array<string, string> $paths */
        $paths = $this->config['paths'] ?? [];
        $remotePath = ($paths['inventory_feed'] ?? '/store/listing/product-inventory') . '/' . $filename;

        $this->sftp->uploadFile($remotePath, $csvContent);

        MipFeed::query()->create([
            'ebay_credential_id' => $credentialId,
            'feed_type' => 'product_inventory',
            'remote_path' => $remotePath,
            'item_count' => count($rows),
            'status' => 'uploaded',
            'uploaded_at' => now(),
        ]);

        return ['filename' => $remotePath, 'count' => count($rows)];
    }
}
