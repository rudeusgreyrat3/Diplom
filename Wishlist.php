<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Wishlist extends Model
{
    const HOTELKI_TITLE = 'Мои хотелки';
    const HOTELKI_SLUG  = 'hotelki';

    protected $fillable = ['user_id', 'title', 'slug', 'is_hotelki', 'event_date', 'description', 'contacts'];

    protected $casts = [
        'event_date' => 'date',
        'is_hotelki' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wishes()
    {
        return $this->hasMany(Wish::class);
    }

    /** Мероприятие уже прошло (сегодняшний день ещё считается актуальным). */
    public function isFinished(): bool
    {
        return !$this->is_hotelki
            && $this->event_date !== null
            && $this->event_date->lt(today());
    }

    /** Найти или создать «Мои хотелки» пользователя. */
    public static function hotelkiFor(User $user): self
    {
        $existing = $user->wishlists()->where('is_hotelki', true)->first();
        if ($existing) {
            return $existing;
        }

        // на случай, если у пользователя уже есть обычный список со slug "hotelki"
        $slug = self::HOTELKI_SLUG;
        $i = 1;
        while ($user->wishlists()->where('slug', $slug)->exists()) {
            $slug = self::HOTELKI_SLUG . '-' . (++$i);
        }

        return $user->wishlists()->create([
            'title'      => self::HOTELKI_TITLE,
            'slug'       => $slug,
            'is_hotelki' => true,
        ]);
    }

    /**
     * Перенести все незабронированные желания этого списка в «Мои хотелки».
     * Возвращает количество перенесённых желаний.
     */
    public function moveUnbookedToHotelki(): int
    {
        if ($this->is_hotelki) {
            return 0;
        }

        $ids = $this->wishes()->doesntHave('booking')->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        $hotelki = self::hotelkiFor($this->user);

        // update() без событий модели: created_at сохраняется, меняется только список
        Wish::whereIn('id', $ids)->update(['wishlist_id' => $hotelki->id]);

        return $ids->count();
    }

    /**
     * Пройтись по прошедшим мероприятиям пользователя и забрать из них
     * всё незабронированное в «Мои хотелки». Возвращает сколько перенесено.
     */
    public static function sweepExpired(User $user): int
    {
        $expired = $user->wishlists()
            ->where('is_hotelki', false)
            ->whereNotNull('event_date')
            ->whereDate('event_date', '<', today())
            ->whereHas('wishes', fn ($q) => $q->doesntHave('booking'))
            ->get();

        $moved = 0;
        foreach ($expired as $wishlist) {
            $moved += $wishlist->moveUnbookedToHotelki();
        }

        return $moved;
    }

    /** Удалить список вместе с оставшимися в нём желаниями и их картинками. */
    public function deleteWithWishes(): void
    {
        foreach ($this->wishes as $wish) {
            if ($wish->image_path) {
                Storage::disk('public')->delete($wish->image_path);
            }
        }

        $this->delete(); // желания и брони удалятся каскадно
    }
}
