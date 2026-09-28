<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestStatusHistory extends Model
{
    protected $table = 'request_status_history';
    public $timestamps = false;
    protected $fillable = ['request_id', 'from_status', 'to_status', 'user_id', 'comment', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
