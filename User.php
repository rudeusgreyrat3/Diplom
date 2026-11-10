<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'avatar',
        'birthday',
    ];

    protected $hidden = [
        'password',
    ];

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function wishes()
    {
        return $this->hasManyThrough(Wish::class, Wishlist::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function messages()
    {
        return $this->hasMany(UserMessage::class);
    }

    public function unreadMessagesCount(): int
    {
        return $this->messages()->whereNull('read_at')->count();
    }

    public function activeBan(): ?Ban
    {
        return Ban::activeForUser($this->id);
    }
}