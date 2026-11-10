<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WishlistController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'event_date'  => 'nullable|date',
            'description' => 'nullable|string|max:1000',
            'contacts'    => 'nullable|string|max:1000',
        ]);

        $base = Str::slug($validated['title']) ?: 'list';
        $slug = $base;
        $i = 1;
        while (Auth::user()->wishlists()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }

        Auth::user()->wishlists()->create($validated + ['slug' => $slug]);

        return redirect()->route('dashboard')->with('success', 'Вишлист создан!');
    }

    public function update(Request $request, Wishlist $wishlist)
    {
        if ($wishlist->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'event_date'  => 'nullable|date',
            'description' => 'nullable|string|max:1000',
            'contacts'    => 'nullable|string|max:1000',
        ]);

        // у «Моих хотелок» нет даты — они бессрочные
        if ($wishlist->is_hotelki) {
            $validated['event_date'] = null;
        }

        $wishlist->update($validated);

        return redirect()->route('dashboard')->with('success', 'Вишлист обновлён!');
    }

    public function destroy(Wishlist $wishlist)
    {
        if ($wishlist->user_id !== Auth::id()) {
            abort(403);
        }

        // всё, что никто не забронировал, не пропадает, а переезжает в «Мои хотелки»
        $moved = $wishlist->moveUnbookedToHotelki();

        $wishlist->load('wishes');
        $wishlist->deleteWithWishes();

        $message = 'Вишлист удалён.';
        if ($moved > 0) {
            $message .= ' ' . $moved . ' ' . self::wishWord($moved) . ' без брони перенесено в «' . Wishlist::HOTELKI_TITLE . '».';
        }

        return redirect()->route('dashboard')->with('success', $message);
    }

    public static function wishWord(int $n): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;
        if ($mod10 === 1 && $mod100 !== 11) return 'желание';
        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) return 'желания';
        return 'желаний';
    }
}