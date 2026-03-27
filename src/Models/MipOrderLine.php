<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $mip_order_id
 * @property string $line_item_id
 * @property string $item_id
 * @property string|null $sku
 * @property string $title
 * @property int $quantity
 * @property string $unit_price
 * @property string|null $currency
 * @property string|null $logistics_status
 * @property string|null $fulfillment_status
 * @property string|null $tracking_number
 * @property string|null $shipping_carrier
 * @property \Illuminate\Support\Carbon|null $shipped_at
 * @property \Illuminate\Support\Carbon|null $fulfilled_at
 * @property array<string, mixed>|null $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static Builder<static> unfulfilled()
 * @method static Builder<static> shipped()
 * @method static Builder<static> pendingExport()
 */
final class MipOrderLine extends Model
{
    protected $table = 'mip_order_lines';

    protected $fillable = [
        'mip_order_id',
        'line_item_id',
        'item_id',
        'sku',
        'title',
        'quantity',
        'unit_price',
        'currency',
        'logistics_status',
        'fulfillment_status',
        'tracking_number',
        'shipping_carrier',
        'shipped_at',
        'fulfilled_at',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'shipped_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<MipOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(MipOrder::class, 'mip_order_id');
    }

    /**
     * Lines that have not been fulfilled on eBay yet.
     *
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeUnfulfilled(Builder $query): Builder
    {
        return $query->whereNull('fulfilled_at');
    }

    /**
     * Lines that have been marked as shipped (by a plugin).
     *
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeShipped(Builder $query): Builder
    {
        return $query->where('fulfillment_status', 'SHIPPED');
    }

    /**
     * Lines shipped but not yet exported to eBay.
     *
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopePendingExport(Builder $query): Builder
    {
        return $query->where('fulfillment_status', 'SHIPPED')
            ->whereNull('fulfilled_at');
    }

    /**
     * Mark this line item as shipped with tracking info.
     */
    public function markShipped(string $carrier, string $trackingNumber): void
    {
        $this->fulfillment_status = 'SHIPPED';
        $this->shipping_carrier = $carrier;
        $this->tracking_number = $trackingNumber;
        $this->shipped_at = now();
        $this->save();
    }

    /**
     * Mark this line item as fulfilled on eBay (CSV uploaded).
     */
    public function markFulfilledOnEbay(): void
    {
        $this->fulfilled_at = now();
        $this->save();
    }
}
