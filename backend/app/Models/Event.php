<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'description', 'venue', 'location_id', 'starts_on', 'ends_on', 'status', 'notes', 'opened_at', 'closed_at'];
    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'opened_at' => 'datetime', 'closed_at' => 'datetime'];
    public function allocations(): HasMany { return $this->hasMany(EventAllocation::class); }
}
