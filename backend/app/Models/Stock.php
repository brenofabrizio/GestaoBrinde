<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    protected $table = 'stock';
    public $timestamps = false;
    protected $fillable = ['item_id', 'qty_on_hand', 'qty_reserved', 'updated_at'];
    protected $casts = ['qty_on_hand' => 'integer', 'qty_reserved' => 'integer'];

    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function movements(): HasMany { return $this->hasMany(StockMovement::class, 'item_id', 'item_id'); }
}
