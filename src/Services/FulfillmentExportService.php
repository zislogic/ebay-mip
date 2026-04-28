<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Services;

use Illuminate\Database\Eloquent\Collection;
use Zislogic\Ebay\Mip\Csv\CsvWriter;
use Zislogic\Ebay\Mip\Exceptions\MipException;
use Zislogic\Ebay\Mip\Sftp\MipSftpClient;
use Zislogic\Ebay\Model\Fulfillment\Models\FulfillmentOrderLine;

final class FulfillmentExportService
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly MipSftpClient $sftp,
        private readonly CsvWriter $csvWriter,
        private readonly array $config,
    ) {}

    /**
     * Export all shipped-but-not-yet-fulfilled lines for a credential.
     *
     * @return array{filename: string, count: int}
     *
     * @throws MipException
     */
    public function export(int $credentialId): array
    {
        /** @var Collection<int, FulfillmentOrderLine> $lines */
        $lines = FulfillmentOrderLine::query()
            ->pendingExport()
            ->whereHas('order', function ($query) use ($credentialId): void {
                $query->where('ebay_credential_id', $credentialId);
            })
            ->with('order')
            ->get();

        if ($lines->isEmpty()) {
            return ['filename' => '', 'count' => 0];
        }

        return $this->uploadLines($lines);
    }

    /**
     * Export specific order lines.
     *
     * @param  Collection<int, FulfillmentOrderLine>  $lines
     * @return array{filename: string, count: int}
     *
     * @throws MipException
     */
    public function exportLines(Collection $lines): array
    {
        if ($lines->isEmpty()) {
            return ['filename' => '', 'count' => 0];
        }

        return $this->uploadLines($lines);
    }

    /**
     * Build CSV, upload to SFTP, and mark lines as fulfilled.
     *
     * @param  Collection<int, FulfillmentOrderLine>  $lines
     * @return array{filename: string, count: int}
     *
     * @throws MipException
     */
    private function uploadLines(Collection $lines): array
    {
        /** @var array<int, string> $headers */
        $headers = $this->config['fulfillment_headers'] ?? [
            'Order ID',
            'Line Item ID',
            'Logistics Status',
            'Shipment Carrier',
            'Shipment Tracking',
        ];

        $rows = [];

        foreach ($lines as $line) {
            $order = $line->order;

            if ($order === null) {
                continue;
            }

            $rows[] = [
                $order->order_id,
                $line->line_item_id,
                $line->fulfillment_status ?? 'SHIPPED',
                $line->shipping_carrier ?? '',
                $line->tracking_number ?? '',
            ];
        }

        $csvContent = $this->csvWriter->generate($headers, $rows);

        $filename = 'fulfillment_'.date('Ymd_His').'.csv';

        /** @var array<string, string> $paths */
        $paths = $this->config['paths'] ?? [];
        $fulfillmentPath = (string) ($paths['order_fulfillment'] ?? '/store/order-fulfillment');

        $remotePath = $fulfillmentPath.'/'.$filename;

        $this->sftp->uploadFile($remotePath, $csvContent);

        // Mark lines as fulfilled
        foreach ($lines as $line) {
            $line->markFulfilled();
        }

        return [
            'filename' => $remotePath,
            'count' => count($rows),
        ];
    }
}
