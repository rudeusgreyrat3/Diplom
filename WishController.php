<?php

namespace App\Http\Controllers;

use App\Models\Wish;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class WishController extends Controller
{
    // Личный кабинет — список своих вишлистов с желаниями внутри каждого
public function index()
{
    // мероприятия, которые уже прошли, отдают незабронированное в «Мои хотелки»
    $moved = Wishlist::sweepExpired(Auth::user());
    if ($moved > 0) {
        session()->now('success', $moved . ' ' . WishlistController::wishWord($moved)
            . ' из прошедших событий перенесено в «' . Wishlist::HOTELKI_TITLE . '» ✨');
    }

    $wishlists = Auth::user()->wishlists()
        ->with('wishes.booking')
        ->orderByDesc('is_hotelki') // хотелки всегда первыми
        ->latest()
        ->get();

    return view('dashboard.index', compact('wishlists'));
}

    // Сохранить новое желание
    public function store(Request $request)
{
    $validated = $request->validate([
        'wishlist_id' => 'required|exists:wishlists,id',
        'title'   => 'required|string|max:255',
        'price'   => 'nullable|numeric|min:0',
        'link'    => 'nullable|url|max:2048',
        'comment' => 'nullable|string|max:1000',
        'image'   => 'nullable|image|max:4096',
    ]);

    $wishlist = Auth::user()->wishlists()->findOrFail($validated['wishlist_id']);

    if ($wishlist->isFinished()) {
        return redirect()->route('dashboard')->with('error',
            'Мероприятие «' . $wishlist->title . '» уже прошло. Добавь желание в «' . Wishlist::HOTELKI_TITLE . '» или измени дату списка.');
    }

    $imagePath = null;
    if ($request->hasFile('image')) {
        $imagePath = $request->file('image')->store('wishes', 'public');
    }

    $wishlist->wishes()->create([
        'user_id'    => Auth::id(),
        'title'      => $validated['title'],
        'price'      => $validated['price'] ?? null,
        'link'       => $validated['link'] ?? null,
        'comment'    => $validated['comment'] ?? null,
        'image_path' => $imagePath,
    ]);

    return redirect()->route('dashboard')->with('success', 'Желание добавлено!');
}

    // Обновить желание
    public function update(Request $request, Wish $wish)
    {
        if ($wish->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title'   => 'required|string|max:255',
            'price'   => 'nullable|numeric|min:0',
            'link'    => 'nullable|url|max:2048',
            'comment' => 'nullable|string|max:1000',
            'image'   => 'nullable|image|max:4096',
        ]);

        if ($request->hasFile('image')) {
            if ($wish->image_path) {
                Storage::disk('public')->delete($wish->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('wishes', 'public');
        }

        $wish->update($validated);

        return redirect()->route('dashboard')->with('success', 'Желание обновлено!');
    }

    // Удалить желание
    public function destroy(Wish $wish)
    {
        if ($wish->user_id !== Auth::id()) {
            abort(403);
        }

        if ($wish->image_path) {
            Storage::disk('public')->delete($wish->image_path);
        }

        $wish->delete();

        return redirect()->route('dashboard')->with('success', 'Желание удалено!');
    }

    // Владелец сбрасывает бронь (например, если друг передумал в переписке)
    public function unbook(Wish $wish)
    {
        if ($wish->user_id !== Auth::id()) {
            abort(403);
        }

        $wish->booking()?->delete();

        return redirect()->route('dashboard')->with('success', 'Бронь снята.');
    }
}