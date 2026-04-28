<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Zislogic\Ebay\Mip\Csv\CsvReader;
use Zislogic\Ebay\Mip\Services\OrderImportService;
use Zislogic\Ebay\Mip\Sftp\MipSftpClient;
use Zislogic\Ebay\Mip\Tests\TestCase;
use Zislogic\Ebay\Model\Fulfillment\Models\FulfillmentOrder;
use Zislogic\Ebay\Model\Fulfillment\Models\FulfillmentOrderLine;

final class OrderImportServiceTest extends TestCase
{
    #[Test]
    public function it_imports_orders_from_csv(): void
    {
        $credential = $this->createCredential();
        $csvContent = $this->getFixtureContent('sample-order-report.csv');

        /** @var array<string, mixed> $config */
        $config = config('ebay-mip');

        $sftp = \Mockery::mock(MipSftpClient::class);
        $csvReader = new CsvReader;

        $service = new OrderImportService($sftp, $csvReader, $config);

        $count = $service->importFromCsv($csvContent, $credential->id);

        // 4 CSV rows → 3 unique orders (ORD-001, ORD-002, ORD-003)
        $this->assertSame(3, $count);

        $this->assertDatabaseHas('fulfillment_orders', [
            'ebay_credential_id' => $credential->id,
            'order_id' => 'ORD-001',
            'buyer_user_id' => 'buyer_hans',
        ]);

        $this->assertDatabaseHas('fulfillment_orders', [
            'order_id' => 'ORD-002',
            'buyer_user_id' => 'buyer_anna',
        ]);

        $this->assertDatabaseHas('fulfillment_orders', [
            'order_id' => 'ORD-003',
            'buyer_user_id' => 'buyer_peter',
        ]);
    }

    #[Test]
    public function it_creates_line_items_for_orders(): void
    {
        $credential = $this->createCredential();
        $csvContent = $this->getFixtureContent('sample-order-report.csv');

        /** @var array<string, mixed> $config */
        $config = config('ebay-mip');

        $sftp = \Mockery::mock(MipSftpClient::class);
        $service = new OrderImportService($sftp, new CsvReader, $config);

        $service->importFromCsv($csvContent, $credential->id);

        // ORD-002 has 2 line items
        /** @var FulfillmentOrder $order2 */
        $order2 = FulfillmentOrder::query()->where('order_id', 'ORD-002')->first();
        $this->assertCount(2, $order2->lines);

        $this->assertDatabaseHas('fulfillment_order_lines', [
            'fulfillment_order_id' => $order2->id,
            'line_item_id' => 'LI-002-1',
            'sku' => 'TOW-AUDI-Q7',
        ]);

        $this->assertDatabaseHas('fulfillment_order_lines', [
            'fulfillment_order_id' => $order2->id,
            'line_item_id' => 'LI-002-2',
            'sku' => 'WIR-AUDI-Q7',
        ]);
    }

    #[Test]
    public function it_maps_essential_columns_correctly(): void
    {
        $credential = $this->createCredential();
        $csvContent = $this->getFixtureContent('sample-order-report.csv');

        /** @var array<string, mixed> $config */
        $config = config('ebay-mip');

        $sftp = \Mockery::mock(MipSftpClient::class);
        $service = new OrderImportService($sftp, new CsvReader, $config);

        $service->importFromCsv($csvContent, $credential->id);

        /** @var FulfillmentOrder $order */
        $order = FulfillmentOrder::query()->where('order_id', 'ORD-001')->first();

        $this->assertSame('buyer_hans', $order->buyer_user_id);
        $this->assertSame('hans@example.de', $order->buyer_email);
        $this->assertSame('Hans Mueller', $order->buyer_name);
        $this->assertSame('PAID', $order->payment_status);
        $this->assertSame('NOT_STARTED', $order->order_status);
        $this->assertSame('EUR', $order->currency);
        $this->assertSame('259.98', $order->total_price);
        $this->assertSame('Hans Mueller', $order->ship_to_name);
        $this->assertSame('Musterstr. 1', $order->ship_to_street1);
        $this->assertSame('Berlin', $order->ship_to_city);
        $this->assertSame('10115', $order->ship_to_zip);
        $this->assertSame('DE', $order->ship_to_country);

        // Line item
        /** @var FulfillmentOrderLine $line */
        $line = $order->lines->first();
        $this->assertSame('LI-001-1', $line->line_item_id);
        $this->assertSame('ITEM001', $line->item_id);
        $this->assertSame('TOW-BMW-X5', $line->sku);
        $this->assertSame(1, $line->quantity);
        $this->assertSame('249.99', $line->unit_price);
        $this->assertSame('NOT_STARTED', $line->logistics_status);
    }

    #[Test]
    public function it_stores_unmapped_columns_in_meta(): void
    {
        $credential = $this->createCredential();
        $csvContent = $this->getFixtureContent('sample-order-report.csv');

        /** @var array<string, mixed> $config */
        $config = config('ebay-mip');

        $sftp = \Mockery::mock(MipSftpClient::class);
        $service = new OrderImportService($sftp, new CsvReader, $config);

        $service->importFromCsv($csvContent, $credential->id);

        /** @var FulfillmentOrder $order */
        $order = FulfillmentOrder::query()->where('order_id', 'ORD-001')->first();

        $this->assertNotNull($order->meta);
        $this->assertIsArray($order->meta);

        // These columns are NOT in the mapping, so they should be in meta
        $this->assertSame('EBAY_DE', $order->meta['channelID'] ?? null);
        $this->assertSame('seller_test', $order->meta['sellerID'] ?? null);
    }

    #[Test]
    public function it_upserts_existing_orders(): void
    {
        $credential = $this->createCredential();
        $csvContent = $this->getFixtureContent('sample-order-report.csv');

        /** @var array<string, mixed> $config */
        $config = config('ebay-mip');

        $sftp = \Mockery::mock(MipSftpClient::class);
        $service = new OrderImportService($sftp, new CsvReader, $config);

        // Import twice
        $service->importFromCsv($csvContent, $credential->id);
        $service->importFromCsv($csvContent, $credential->id);

        // Should still be 3 orders, not 6
        $this->assertSame(3, FulfillmentOrder::query()->count());

        // ORD-002 should still have 2 lines, not 4
        /** @var FulfillmentOrder $order2 */
        $order2 = FulfillmentOrder::query()->where('order_id', 'ORD-002')->first();
        $this->assertCount(2, $order2->lines);
    }

    #[Test]
    public function it_preserves_fulfillment_data_on_reimport(): void
    {
        $credential = $this->createCredential();
        $csvContent = $this->getFixtureContent('sample-order-report.csv');

        /** @var array<string, mixed> $config */
        $config = config('ebay-mip');

        $sftp = \Mockery::mock(MipSftpClient::class);
        $service = new OrderImportService($sftp, new CsvReader, $config);

        // First import
        $service->importFromCsv($csvContent, $credential->id);

        // Simulate plugin marking a line as shipped
        /** @var FulfillmentOrder $order */
        $order = FulfillmentOrder::query()->where('order_id', 'ORD-001')->first();

        /** @var FulfillmentOrderLine $line */
        $line = $order->lines->first();
        $line->markShipped('DHL', 'TRACK123');

        // Re-import the same CSV
        $service->importFromCsv($csvContent, $credential->id);

        // Fulfillment data should be preserved
        $line->refresh();
        $this->assertSame('SHIPPED', $line->fulfillment_status);
        $this->assertSame('DHL', $line->shipping_carrier);
        $this->assertSame('TRACK123', $line->tracking_number);
        $this->assertNotNull($line->shipped_at);
    }

    #[Test]
    public function it_returns_zero_for_empty_csv(): void
    {
        $credential = $this->createCredential();

        /** @var array<string, mixed> $config */
        $config = config('ebay-mip');

        $sftp = \Mockery::mock(MipSftpClient::class);
        $service = new OrderImportService($sftp, new CsvReader, $config);

        $count = $service->importFromCsv('', $credential->id);

        $this->assertSame(0, $count);
    }
}
