<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['order_id', 'payment_method', 'status', 'amount', 'transaction_id', 'gcash_proof'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function order() {
        return $this->belongsTo(Order::class);
    }
}
