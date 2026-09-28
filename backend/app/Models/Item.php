<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'code', 'name', 'description', 'category_id', 'location_id', 'supplier_id',
        'kind', 'unit_value', 'min_stock', 'status', 'entry_date', 'notes',
    ];

    protected function casts(): array
    {
        return ['unit_value' => 'decimal:2', 'entry_date' => 'date', 'min_stock' => 'integer'];
    }

    public function stock(): HasOne
    {
        return $this->hasOne(Stock::class);
    }
}
