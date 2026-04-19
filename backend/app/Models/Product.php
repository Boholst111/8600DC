<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'description', 'image_url', 'brand', 'scale', 'series', 
        'price', 'stock', 'is_limited_edition', 'has_opening_parts', 
        'tire_type', 'is_preorder', 'eta', 'downpayment_amount'
    ];

    protected $casts = [
        'is_limited_edition' => 'boolean',
        'has_opening_parts' => 'boolean',
        'is_preorder' => 'boolean',
        'price' => 'decimal:2',
        'downpayment_amount' => 'decimal:2',
    ];

    public function category() {
        return $this->belongsTo(Category::class);
    }

    public function preorder() {
        return $this->hasOne(Preorder::class);
    }

    public function images() {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }
}
