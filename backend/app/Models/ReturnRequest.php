<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'user_id', 'reason', 'description',
        'evidence_photo', 'evidence_files', 'items', 'status', 'resolution',
        'refund_amount', 'admin_notes', 'reviewed_by',
        'reviewed_at', 'resolved_at',
    ];

    protected $casts = [
        'items'         => 'array',
        'evidence_files' => 'array',
        'refund_amount' => 'decimal:2',
        'reviewed_at'   => 'datetime',
        'resolved_at'   => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
