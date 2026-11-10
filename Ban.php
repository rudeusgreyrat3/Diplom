<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Ban extends Model
{
    protected $fillable = ['user_id', 'token', 'display_name', 'reason', 'until', 'lifted_at', 'complaint_id'];

    protected $casts = [
        'until'     => 'datetime',
        'lifted_at' => 'datetime',
    ];

    public function user()      { return $this->belongsTo(User::class); }
    public function complaint() { return $this->belongsTo(Complaint::class); }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('lifted_at')
            ->where(fn ($q) => $q->whereNull('until')->orWhere('until', '>', now()));
    }

    public static function activeForUser(?int $userId): ?self
    {
        return $userId ? self::active()->where('user_id', $userId)->latest('id')->first() : null;
    }

    public static function activeForToken(?string $token): ?self
    {
        return $token ? self::active()->where('token', $token)->latest('id')->first() : null;
    }

    public function isActive(): bool
    {
        return $this->lifted_at === null && ($this->until === null || $this->until->isFuture());
    }

    /** «до 12.10.2026 18:00» или «навсегда» */
    public function untilText(): string
    {
        return $this->until ? 'до ' . $this->until->format('d.m.Y H:i') : 'навсегда';
    }
}
