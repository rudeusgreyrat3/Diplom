<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wish extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wishlist_id',
        'title',
        'comment',
        'price',
        'link',
        'image_path',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wishlist()
    {
        return $this->belongsTo(Wishlist::class);
    }

    public function booking()
    {
        return $this->hasOne(Booking::class);
    }

    public function getIsBookedAttribute(): bool
    {
        return $this->booking !== null;
    }
}