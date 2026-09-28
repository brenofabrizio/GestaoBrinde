<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryItem extends Model
{
    protected $fillable = ['delivery_id', 'item_id', 'qty', 'balance_after'];
    public function delivery(): BelongsTo { return $this->belongsTo(Delivery::class); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
}
