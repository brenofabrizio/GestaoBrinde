<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TradeRequest extends Model
{
    use SoftDeletes;
    protected $table = 'requests';
    protected $fillable = [
        'code', 'requester_id', 'department_id', 'industry_id', 'purpose', 'recipient',
        'needed_date', 'purchase_ticket_no', 'flow', 'needs_purchase', 'status', 'total_value',
        'notes', 'submitted_at', 'approved_at', 'rejected_at', 'cancelled_at',
    ];

    protected $casts = [
        'needed_date' => 'date',
        'total_value' => 'decimal:2',
        'needs_purchase' => 'boolean',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requester_id'); }
    public function items(): HasMany { return $this->hasMany(TradeRequestItem::class, 'request_id'); }
    public function history(): HasMany { return $this->hasMany(RequestStatusHistory::class, 'request_id'); }
    public function approvals(): HasMany { return $this->hasMany(RequestApproval::class, 'request_id'); }
}
