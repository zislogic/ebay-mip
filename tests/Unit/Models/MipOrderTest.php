<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Tests\Unit\Models;

use PHPUnit\Framework\Attributes\Test;
use Zislogic\Ebay\Mip\Models\MipOrder;
use Zislogic\Ebay\Mip\Models\MipOrderLine;
use Zislogic\Ebay\Mip\Tests\TestCase;

final class MipOrderTest extends TestCase
{
    #[Test]
    public function it_creates_order_with_attributes(): void
    {
        $credential = $this->createCredential();

        $order = $this->createOrder($credential->id, [
            'order_id' => 'ORD-TEST-001',
            'buyer_user_id' => 'buyer_test',
            'total_price' => '249.99',
            'currency' => 'EUR',
        ]);

        $this->assertDatabaseHas('mip_orders', [
            'order_id' => 'ORD-TEST-001',
            'buyer_user_id' => 'buyer_test',
        ]);

        $this->assertSame('249.99', $order->total_price);
        $this->assertSame('EUR', $order->currency);
    }

    #[Test]
    public function it_has_lines_relationship(): void
    {
        $credential = $this->createCredential();
        $order = $this->createOrder($credential->id);

        MipOrderLine::query()->create([
            'mip_order_id' => $order->id,
            'line_item_id' => 'LI-001',
            'item_id' => 'ITEM001',
            'title' => 'Test Product',
            'quantity' => 2,
            'unit_price' => '49.99',
        ]);

        MipOrderLine::query()->create([
            'mip_order_id' => $order->id,
            'line_item_id' => 'LI-002',
            'item_id' => 'ITEM002',
            'title' => 'Another Product',
            'quantity' => 1,
            'unit_price' => '19.99',
        ]);

        $this->assertCount(2, $order->lines);
    }

    #[Test]
    public function it_belongs_to_credential(): void
    {
        $credential = $this->createCredential();
        $order = $this->createOrder($credential->id);

        $this->assertSame($credential->id, $order->credential->id);
    }

    #[Test]
    public function it_casts_meta_to_array(): void
    {
        $credential = $this->createCredential();
        $order = $this->createOrder($credential->id, [
            'meta' => ['buyerTaxId' => 'DE123456789', 'fulfillmentType' => 'SHIP_TO'],
        ]);

        $order->refresh();

        $this->assertIsArray($order->meta);
        $this->assertSame('DE123456789', $order->meta['buyerTaxId']);
    }

    #[Test]
    public function it_scopes_for_credential(): void
    {
        $credential1 = $this->createCredential();
        $credential2 = $this->createCredential();

        $this->createOrder($credential1->id);
        $this->createOrder($credential1->id);
        $this->createOrder($credential2->id);

        $this->assertCount(2, MipOrder::query()->forCredential($credential1->id)->get());
        $this->assertCount(1, MipOrder::query()->forCredential($credential2->id)->get());
    }

    #[Test]
    public function it_enforces_unique_constraint(): void
    {
        $credential = $this->createCredential();

        $this->createOrder($credential->id, ['order_id' => 'ORD-UNIQUE']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->createOrder($credential->id, ['order_id' => 'ORD-UNIQUE']);
    }

    #[Test]
    public function it_casts_datetime_fields(): void
    {
        $credential = $this->createCredential();
        $order = $this->createOrder($credential->id, [
            'ordered_at' => '2025-01-15T10:30:00Z',
            'paid_at' => '2025-01-15T11:00:00Z',
        ]);

        $order->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $order->ordered_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $order->paid_at);
    }
}
