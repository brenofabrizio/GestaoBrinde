<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    protected $fillable = [
        'code', 'type', 'request_id', 'event_id', 'industry_id', 'delivered_by', 'received_by_name',
        'received_by_document', 'received_by_email', 'received_by_phone', 'signature_hash', 'signature_path',
        'verification_hash', 'idempotency_key', 'idempotency_payload_hash', 'notes',
    ];

    protected $hidden = ['signature_path', 'signature_hash', 'verification_hash', 'idempotency_key', 'idempotency_payload_hash'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(TradeRequest::class, 'request_id');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }
}
