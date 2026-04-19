<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Preorder extends Model
{
    protected $fillable = ['product_id', 'release_date', 'downpayment_amount', 'is_active'];

    protected $casts = [
        'release_date' => 'date',
        'is_active' => 'boolean',
        'downpayment_amount' => 'decimal:2',
    ];

    public function product() {
        return $this->belongsTo(Product::class);
    }
}
