<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Tests\Feature;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Zislogic\Ebay\Mip\Csv\CsvWriter;
use Zislogic\Ebay\Mip\Models\MipOrderLine;
use Zislogic\Ebay\Mip\Services\FulfillmentExportService;
use Zislogic\Ebay\Mip\Sftp\MipSftpClient;
use Zislogic\Ebay\Mip\Tests\TestCase;

final class FulfillmentExportServiceTest extends TestCase
{
    #[Test]
    public function it_exports_shipped_lines_as_csv(): void
    {
        $credential = $this->createCredential();
        $order = $this->createOrder($credential->id, ['order_id' => 'ORD-001']);

        MipOrderLine::query()->create([
            'mip_order_id' => $order->id,
            'line_item_id' => 'LI-001',
            'item_id' => 'ITEM001',
            'title' => 'Test Product',
            'quantity' => 1,
            'unit_price' => '99.99',
            'fulfillment_status' => 'SHIPPED',
            'shipping_carrier' => 'DHL',
            'tracking_number' => 'TRACK123',
            'shipped_at' => now(),
        ]);

        /** @var array<string, mixed> $config */
        $config = config('ebay-mip');

        $uploadedContent = '';

        $sftp = Mockery::mock(MipSftpClient::class);
        $sftp->shouldReceive('uploadFile')
            ->once()
            ->andReturnUsing(function (string $path, string $content) use (&$uploadedContent): void {
                $uploadedContent = $content;
            });

        $service = new FulfillmentExportService($sftp, new CsvWriter(), $config);

        $result = $service->export($credential->id);

        $this->assertSame(1, $result['count']);
        $this->assertNotEmpty($result['filename']);
        $this->assertStringContainsString('fulfillment_', $result['filename']);
        $this->assertStringContainsString('.csv', $result['filename']);

        // Verify CSV content
        $this->assertStringContainsString('Order ID', $uploadedContent);
        $this->assertStringContainsString('ORD-001', $uploadedContent);
        $this->assertStringContainsString('LI-001', $uploadedContent);
        $this->assertStringContainsString('SHIPPED', $uploadedContent);
        $this->assertStringContainsString('DHL', $uploadedContent);
        $this->assertStringContainsString('TRACK123', $uploadedContent);
    }

    #[Test]
    public function it_marks_lines_as_fulfilled_after_export(): void
    {
        $credential = $this->createCredential();
        $order = $this->createOrder($credential->id);

        $line = MipOrderLine::query()->create([
            'mip_order_id' => $order->id,
            'line_item_id' => 'LI-001',
            'item_id' => 'ITEM001',
            'title' => 'Test',
            'quantity' => 1,
            'unit_price' => '99.99',
            'fulfillment_status' => 'SHIPPED',
            'shipping_carrier' => 'DHL',
            'tracking_number' => 'TRACK123',
        ]);

        /** @var array<string, mixed> $config */
        $config = config('ebay-mip');

        $sftp = Mockery::mock(MipSftpClient::class);
        $sftp->shouldReceive('uploadFile')->once();

        $service = new FulfillmentExportService($sftp, new CsvWriter(), $config);
        $service->export($credential->id);

        $line->refresh();
        $this->assertNotNull($line->fulfilled_at);
    }

    #[Test]
    public function it_skips_already_fulfilled_lines(): void
    {
        $credential = $this->createCredential();
        $order = $this->createOrder($credential->id);

        // Already fulfilled
        MipOrderLine::query()->create([
            'mip_order_id' => $order->id,
            'line_item_id' => 'LI-001',
            'item_id' => 'ITEM001',
            'title' => 'Already Done',
            'quantity' => 1,
            'unit_price' => '99.99',
            'fulfillment_status' => 'SHIPPED',
            'fulfilled_at' => now(),
        ]);

        /** @var array<string, mixed> $config */
        $config = config('ebay-mip');

        $sftp = Mockery::mock(MipSftpClient::class);
        $sftp->shouldNotReceive('uploadFile');

        $service = new FulfillmentExportService($sftp, new CsvWriter(), $config);
        $result = $service->export($credential->id);

        $this->assertSame(0, $result['count']);
    }

    #[Test]
    public function it_skips_non_shipped_lines(): void
    {
        $credential = $this->createCredential();
        $order = $this->createOrder($credential->id);

        MipOrderLine::query()->create([
            'mip_order_id' => $order->id,
            'line_item_id' => 'LI-001',
            'item_id' => 'ITEM001',
            'title' => 'Not Shipped Yet',
            'quantity' => 1,
            'unit_price' => '99.99',
        ]);

        /** @var array<string, mixed> $config */
        $config = config('ebay-mip');

        $sftp = Mockery::mock(MipSftpClient::class);
        $sftp->shouldNotReceive('uploadFile');

        $service = new FulfillmentExportService($sftp, new CsvWriter(), $config);
        $result = $service->export($credential->id);

        $this->assertSame(0, $result['count']);
    }

    #[Test]
    public function it_exports_multiple_lines_from_different_orders(): void
    {
        $credential = $this->createCredential();

        $order1 = $this->createOrder($credential->id, ['order_id' => 'ORD-001']);
        $order2 = $this->createOrder($credential->id, ['order_id' => 'ORD-002']);

        MipOrderLine::query()->create([
            'mip_order_id' => $order1->id,
            'line_item_id' => 'LI-001',
            'item_id' => 'ITEM001',
            'title' => 'Product 1',
            'quantity' => 1,
            'unit_price' => '99.99',
            'fulfillment_status' => 'SHIPPED',
            'shipping_carrier' => 'DHL',
            'tracking_number' => 'TRACK001',
        ]);

        MipOrderLine::query()->create([
            'mip_order_id' => $order2->id,
            'line_item_id' => 'LI-002',
            'item_id' => 'ITEM002',
            'title' => 'Product 2',
            'quantity' => 1,
            'unit_price' => '49.99',
            'fulfillment_status' => 'SHIPPED',
            'shipping_carrier' => 'DPD',
            'tracking_number' => 'TRACK002',
        ]);

        /** @var array<string, mixed> $config */
        $config = config('ebay-mip');

        $sftp = Mockery::mock(MipSftpClient::class);
        $sftp->shouldReceive('uploadFile')->once();

        $service = new FulfillmentExportService($sftp, new CsvWriter(), $config);
        $result = $service->export($credential->id);

        $this->assertSame(2, $result['count']);
    }
}
