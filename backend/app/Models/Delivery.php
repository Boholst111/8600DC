<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = ['order_id', 'user_id', 'status', 'notes'];

    public function order() {
        return $this->belongsTo(Order::class);
    }

    public function rider() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
