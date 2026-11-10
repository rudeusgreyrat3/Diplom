<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    const REASONS = [
        'mass_booking' => 'Бронирует всё подряд / явно шутит',
        'fake_name'    => 'Оскорбительное или фейковое имя',
        'not_gifting'  => 'Забронировал(а), но не собирается дарить',
        'other'        => 'Другое',
    ];

    const STATUSES = [
        'new'      => 'Новая',
        'resolved' => 'Решена',
        'rejected' => 'Отклонена',
    ];

    protected $fillable = [
        'booking_id', 'wish_id', 'reporter_id',
        'accused_user_id', 'accused_token', 'accused_name',
        'wish_title', 'reason', 'comment', 'status', 'resolved_at',
    ];

    protected $casts = ['resolved_at' => 'datetime'];

    public function booking()      { return $this->belongsTo(Booking::class); }
    public function wish()         { return $this->belongsTo(Wish::class); }
    public function reporter()     { return $this->belongsTo(User::class, 'reporter_id'); }
    public function accusedUser()  { return $this->belongsTo(User::class, 'accused_user_id'); }
    public function messages()     { return $this->hasMany(UserMessage::class); }

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Все жалобы на того же нарушителя (аккаунт или гостевой токен). */
    public function scopeSameAccused(Builder $q, Complaint $c): Builder
    {
        if ($c->accused_user_id) {
            return $q->where('accused_user_id', $c->accused_user_id);
        }
        if ($c->accused_token) {
            return $q->whereNull('accused_user_id')->where('accused_token', $c->accused_token);
        }

        return $q->whereKey($c->id); // старая бронь без токена — не знаем, кто это
    }

    /** Можно ли однозначно определить нарушителя (аккаунт или гостевой браузер). */
    public function canIdentifyAccused(): bool
    {
        return $this->accused_user_id !== null || !empty($this->accused_token);
    }

    /** Брони нарушителя (только те, что ещё существуют). */
    public function accusedBookingsQuery()
    {
        if ($this->accused_user_id) {
            return Booking::where('user_id', $this->accused_user_id);
        }
        if ($this->accused_token) {
            return Booking::whereNull('user_id')->where('token', $this->accused_token);
        }

        return Booking::whereKey($this->booking_id ?? 0);
    }
}
