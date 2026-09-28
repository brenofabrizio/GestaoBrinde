<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'item_id', 'type', 'qty', 'balance_after', 'user_id', 'reason', 'notes',
        'document_ref', 'request_id', 'delivery_id', 'event_id', 'industry_id', 'idempotency_key',
    ];

    protected $casts = ['qty' => 'integer', 'balance_after' => 'integer'];

    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
