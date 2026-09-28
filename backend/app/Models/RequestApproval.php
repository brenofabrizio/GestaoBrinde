<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestApproval extends Model
{
    protected $table = 'request_approvals';
    protected $fillable = ['request_id', 'approver_id', 'decision', 'justification', 'decided_at'];
    protected $casts = ['decided_at' => 'datetime'];
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }
}
