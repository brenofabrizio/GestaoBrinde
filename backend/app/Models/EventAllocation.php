<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventAllocation extends Model
{
    protected $fillable = ['event_id', 'industry_id', 'item_id', 'qty_allocated', 'qty_withdrawn'];
    protected $casts = ['qty_allocated' => 'integer', 'qty_withdrawn' => 'integer'];
}
