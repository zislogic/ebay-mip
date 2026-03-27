<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Zislogic\Ebay\Connector\Models\EbayCredential;

/**
 * @property int $id
 * @property int $ebay_credential_id
 * @property string $order_id
 * @property string $buyer_user_id
 * @property string|null $buyer_email
 * @property string|null $buyer_name
 * @property string|null $order_status
 * @property string|null $payment_status
 * @property string|null $currency
 * @property string|null $total_price
 * @property string|null $ship_to_name
 * @property string|null $ship_to_phone
 * @property string|null $ship_to_street1
 * @property string|null $ship_to_street2
 * @property string|null $ship_to_city
 * @property string|null $ship_to_state
 * @property string|null $ship_to_zip
 * @property string|null $ship_to_country
 * @property \Illuminate\Support\Carbon|null $ordered_at
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon $imported_at
 * @property array<string, mixed>|null $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static Builder<static> forCredential(int $credentialId)
 * @method static Builder<static> unfulfilled()
 */
final class MipOrder extends Model
{
    protected $table = 'mip_orders';

    protected $fillable = [
        'ebay_credential_id',
        'order_id',
        'buyer_user_id',
        'buyer_email',
        'buyer_name',
        'order_status',
        'payment_status',
        'currency',
        'total_price',
        'ship_to_name',
        'ship_to_phone',
        'ship_to_street1',
        'ship_to_street2',
        'ship_to_city',
        'ship_to_state',
        'ship_to_zip',
        'ship_to_country',
        'ordered_at',
        'paid_at',
        'imported_at',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_price' => 'decimal:2',
            'ordered_at' => 'datetime',
            'paid_at' => 'datetime',
            'imported_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EbayCredential, $this>
     */
    public function credential(): BelongsTo
    {
        return $this->belongsTo(EbayCredential::class, 'ebay_credential_id');
    }

    /**
     * @return HasMany<MipOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(MipOrderLine::class, 'mip_order_id');
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeForCredential(Builder $query, int $credentialId): Builder
    {
        return $query->where('ebay_credential_id', $credentialId);
    }

    /**
     * Orders that have at least one unfulfilled line item.
     *
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeUnfulfilled(Builder $query): Builder
    {
        return $query->whereHas('lines', function (Builder $q): void {
            $q->whereNull('fulfilled_at');
        });
    }
}
