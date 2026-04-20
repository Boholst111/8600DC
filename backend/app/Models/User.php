<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'loyalty_points',
        'store_credit',
        'is_blocked',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_blocked' => 'boolean',
        ];
    }

    public function role() {
        return $this->belongsTo(Role::class);
    }

    public function orders() {
        return $this->hasMany(Order::class);
    }

    public function wishlists() {
        return $this->hasMany(Wishlist::class);
    }

    public function loyaltyPointsHistory() {
        return $this->hasMany(LoyaltyPoint::class);
    }

    public function addresses() {
        return $this->hasMany(Address::class);
    }
}
