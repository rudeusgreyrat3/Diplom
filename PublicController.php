<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wish;
use App\Models\Wishlist;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class PublicController extends Controller
{
    // Сколько минут хранить cookie с токеном дарителя (~5 лет)
    const TOKEN_TTL_MINUTES = 60 * 24 * 365 * 5;

    // Список вишлистов пользователя — /@username
    public function userLists(string $username)
    {
        $user = User::where('username', $username)->first();

        if (!$user) {
            return response()->view('public.not-found', [
                'message' => 'Такого пользователя не существует',
            ], 404);
        }

        Wishlist::sweepExpired($user);

        $wishlists = $user->wishlists()
            ->withCount('wishes')
            ->orderByDesc('is_hotelki')
            ->latest()
            ->get();

        return view('public.user-lists', compact('user', 'wishlists'));
    }

    // Конкретный вишлист — /@username/slug
    public function show(string $username, string $slug)
    {
        $user = User::where('username', $username)->first();

        if (!$user) {
            return response()->view('public.not-found', [
                'message' => 'Такого пользователя не существует',
            ], 404);
        }

        Wishlist::sweepExpired($user);

        $wishlist = $user->wishlists()->where('slug', $slug)->first();

        if (!$wishlist) {
            return response()->view('public.not-found', [
                'message' => 'Такого вишлиста не существует',
            ], 404);
        }

        $wishes = $wishlist->wishes()->with('booking')->latest()->get();

        return view('public.wishlist', compact('user', 'wishlist', 'wishes'));
    }

    // Забронировать подарок (гость вводит своё имя и, по желанию, контакт)
    public function book(Request $request, Wish $wish)
    {
        if ($wish->booking) {
            return back()->with('error', 'Этот подарок уже забронирован.');
        }

        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'contact' => 'nullable|string|max:255',
        ]);

        $ban = Auth::check()
            ? \App\Models\Ban::activeForUser(Auth::id())
            : \App\Models\Ban::activeForToken($request->cookie('booker_token'));
        if ($ban) {
            return back()->with('error', 'Вам временно запрещено бронировать подарки (' . $ban->untilText() . ').');
        }

        $token = $this->getOrCreateBookerToken($request);

        $booking = $wish->booking()->create([
            'name'    => $validated['name'],
            'contact' => $validated['contact'] ?? null,
            'token'   => $token,
            'user_id' => Auth::id(),
        ]);

        $wish->wishlist->user->notify(new \App\Notifications\WishBooked($wish, $booking));

        return back()->with('success', 'Спасибо! Подарок за вами 🎉');
    }

    // Отменить свою бронь — доступно только тому, кто её создал
    // (для залогиненных сверяем user_id, для анонимных — токен в cookie)
    public function cancelBooking(Request $request, Wish $wish)
    {
        $booking = $wish->booking;

        if (!$booking) {
            return back()->with('error', 'Бронь не найдена.');
        }

        $isOwnBooking = Auth::check()
            ? $booking->user_id === Auth::id()
            : ($request->cookie('booker_token') && $booking->token === $request->cookie('booker_token'));

        if (!$isOwnBooking) {
            return back()->with('error', 'Вы не можете отменить эту бронь.');
        }

        $booking->delete();

        return back()->with('success', 'Бронь отменена.');
    }

    // Список подарков, которые текущий пользователь (или гость-браузер) забронировал у других
    public function myBookings(Request $request)
    {
        if (Auth::check()) {
            $bookings = Booking::where('user_id', Auth::id())->with('wish.user')->latest()->get();
        } else {
            $token = $request->cookie('booker_token');
            $bookings = $token
                ? Booking::where('token', $token)->whereNull('user_id')->with('wish.user')->latest()->get()
                : collect();
        }

        return view('public.my-bookings', compact('bookings'));
    }
    private function getOrCreateBookerToken(Request $request): string
    {
        if ($request->hasCookie('booker_token')) {
            return $request->cookie('booker_token');
        }

        $token = Str::random(40);
        Cookie::queue('booker_token', $token, self::TOKEN_TTL_MINUTES);

        return $token;
    }
}