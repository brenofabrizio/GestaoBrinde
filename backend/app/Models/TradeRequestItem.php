<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeRequestItem extends Model
{
    protected $table = 'request_items';
    protected $fillable = ['request_id', 'item_id', 'qty_requested', 'qty_approved', 'qty_reserved', 'qty_delivered', 'unit_value', 'notes'];
    protected $casts = ['qty_requested' => 'integer', 'qty_approved' => 'integer', 'qty_reserved' => 'integer', 'qty_delivered' => 'integer', 'unit_value' => 'decimal:2'];
    public function request(): BelongsTo { return $this->belongsTo(TradeRequest::class, 'request_id'); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
}
