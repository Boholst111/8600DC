<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'subtotal', 'shipping_fee', 'packaging_type', 'packaging_fee',
        'total_amount', 'store_credit_used', 'balance_due', 'status', 'delivery_type', 'nearest_branch', 'is_preorder', 'shipping_address', 'courier', 'tracking_number'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'packaging_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'store_credit_used' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'is_preorder' => 'boolean',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function items() {
        return $this->hasMany(OrderItem::class);
    }

    public function payment() {
        return $this->hasOne(Payment::class);
    }

    public function delivery() {
        return $this->hasOne(Delivery::class);
    }
}
