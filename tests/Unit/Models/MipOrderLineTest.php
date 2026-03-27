<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Tests\Unit\Models;

use PHPUnit\Framework\Attributes\Test;
use Zislogic\Ebay\Mip\Models\MipOrderLine;
use Zislogic\Ebay\Mip\Tests\TestCase;

final class MipOrderLineTest extends TestCase
{
    #[Test]
    public function it_creates_line_with_attributes(): void
    {
        $credential = $this->createCredential();
        $order = $this->createOrder($credential->id);

        $line = MipOrderLine::query()->create([
            'mip_order_id' => $order->id,
            'line_item_id' => 'LI-001',
            'item_id' => 'ITEM001',
            'sku' => 'TOW-BMW-X5',
            'title' => 'Towbar for BMW X5',
            'quantity' => 1,
            'unit_price' => '249.99',
            'currency' => 'EUR',
        ]);

        $this->assertDatabaseHas('mip_order_lines', [
            'line_item_id' => 'LI-001',
            'sku' => 'TOW-BMW-X5',
        ]);

        $this->assertSame('249.99', $line->unit_price);
        $this->assertSame(1, $line->quantity);
    }

    #[Test]
    public function it_belongs_to_order(): void
    {
        $credential = $this->createCredential();
        $order = $this->createOrder($credential->id);

        $line = MipOrderLine::query()->create([
            'mip_order_id' => $order->id,
            'line_item_id' => 'LI-001',
            'item_id' => 'ITEM001',
            'title' => 'Test Product',
            'quantity' => 1,
            'unit_price' => '99.99',
        ]);

        $this->assertSame($order->id, $line->order->id);
    }

    #[Test]
    public function it_marks_as_shipped(): void
    {
        $credential = $this->createCredential();
        $order = $this->createOrder($credential->id);

        $line = MipOrderLine::query()->create([
            'mip_order_id' => $order->id,
            'line_item_id' => 'LI-001',
            'item_id' => 'ITEM001',
            'title' => 'Test Product',
            'quantity' => 1,
            'unit_price' => '99.99',
        ]);

        $line->markShipped('DHL', 'TRACK123');

        $line->refresh();

        $this->assertSame('SHIPPED', $line->fulfillment_status);
        $this->assertSame('DHL', $line->shipping_carrier);
        $this->assertSame('TRACK123', $line->tracking_number);
        $this->assertNotNull($line->shipped_at);
    }

    #[Test]
    public function it_marks_as_fulfilled_on_ebay(): void
    {
        $credential = $this->createCredential();
        $order = $this->createOrder($credential->id);

        $line = MipOrderLine::query()->create([
            'mip_order_id' => $order->id,
            'line_item_id' => 'LI-001',
            'item_id' => 'ITEM001',
            'title' => 'Test Product',
            'quantity' => 1,
            'unit_price' => '99.99',
            'fulfillment_status' => 'SHIPPED',
        ]);

        $line->markFulfilledOnEbay();

        $line->refresh();

        $this->assertNotNull($line->fulfilled_at);
    }

    #[Test]
    public function it_scopes_unfulfilled(): void
    {
        $credential = $this->createCredential();
        $order = $this->createOrder($credential->id);

        MipOrderLine::query()->create([
            'mip_order_id' => $order->id,
            'line_item_id' => 'LI-001',
            'item_id' => 'ITEM001',
            'title' => 'Unfulfilled',
            'quantity' => 1,
            'unit_price' => '99.99',
        ]);

        MipOrderLine::query()->create([
            'mip_order_id' => $order->id,
            'line_item_id' => 'LI-002',
            'item_id' => 'ITEM002',
            'title' => 'Fulfilled',
            'quantity' => 1,
            'unit_price' => '49.99',
            'fulfilled_at' => now(),
        ]);

        $unfulfilled = MipOrderLine::query()->unfulfilled()->get();
        $this->assertCount(1, $unfulfilled);
        $this->assertSame('LI-001', $unfulfilled->first()->line_item_id);
    }

    #[Test]
    public function it_scopes_pending_export(): void
    {
        $credential = $this->createCredential();
        $order = $this->createOrder($credential->id);

        // Shipped but not exported
        MipOrderLine::query()->create([
            'mip_order_id' => $order->id,
            'line_item_id' => 'LI-001',
            'item_id' => 'ITEM001',
            'title' => 'Pending Export',
            'quantity' => 1,
            'unit_price' => '99.99',
            'fulfillment_status' => 'SHIPPED',
            'fulfilled_at' => null,
        ]);

        // Shipped and exported
        MipOrderLine::query()->create([
            'mip_order_id' => $order->id,
            'line_item_id' => 'LI-002',
            'item_id' => 'ITEM002',
            'title' => 'Already Exported',
            'quantity' => 1,
            'unit_price' => '49.99',
            'fulfillment_status' => 'SHIPPED',
            'fulfilled_at' => now(),
        ]);

        // Not shipped
        MipOrderLine::query()->create([
            'mip_order_id' => $order->id,
            'line_item_id' => 'LI-003',
            'item_id' => 'ITEM003',
            'title' => 'Not Shipped',
            'quantity' => 1,
            'unit_price' => '29.99',
        ]);

        $pending = MipOrderLine::query()->pendingExport()->get();
        $this->assertCount(1, $pending);
        $this->assertSame('LI-001', $pending->first()->line_item_id);
    }

    #[Test]
    public function it_casts_meta_to_array(): void
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
            'meta' => ['giftMessage' => 'Happy Birthday!', 'lineItemPriceLines' => 'unitPrice:99.99'],
        ]);

        $line->refresh();

        $this->assertIsArray($line->meta);
        $this->assertSame('Happy Birthday!', $line->meta['giftMessage']);
    }
}
