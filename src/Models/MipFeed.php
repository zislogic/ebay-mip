<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Zislogic\Ebay\Connector\Models\EbayCredential;

final class MipFeed extends Model
{
    protected $table = 'mip_feeds';

    protected $fillable = [
        'ebay_credential_id',
        'feed_type',
        'remote_path',
        'item_count',
        'status',
        'uploaded_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'item_count' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EbayCredential, $this> */
    public function credential(): BelongsTo
    {
        return $this->belongsTo(EbayCredential::class, 'ebay_credential_id');
    }
}
