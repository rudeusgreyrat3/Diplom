<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ComplaintController extends Controller
{
    /**
     * Гость нажал «Пожаловаться» → middleware auth отправил его на вход,
     * а после входа он попадает сюда и возвращается на вишлист с открытой формой жалобы.
     */
    public function start(Booking $booking)
    {
        $wish = $booking->wish;
        $owner = $wish->wishlist->user;

        return redirect(route('public.wishlist', [$owner->username, $wish->wishlist->slug]) . '#complain-' . $booking->id);
    }

    public function store(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'reason'  => ['required', Rule::in(array_keys(Complaint::REASONS))],
            'comment' => 'nullable|string|max:1000',
        ], [
            'reason.required' => 'Выберите причину жалобы',
        ]);

        $me = Auth::user();

        // на свою же бронь жаловаться нельзя
        $isOwnBooking = $booking->user_id
            ? $booking->user_id === $me->id
            : ($request->cookie('booker_token') && $request->cookie('booker_token') === $booking->token);
        if ($isOwnBooking) {
            return back()->with('error', 'Это ваша собственная бронь 🙂');
        }

        $alreadyReported = Complaint::where('booking_id', $booking->id)
            ->where('reporter_id', $me->id)
            ->where('status', 'new')
            ->exists();
        if ($alreadyReported) {
            return back()->with('error', 'Вы уже пожаловались на эту бронь — администратор её рассматривает.');
        }

        Complaint::create([
            'booking_id'      => $booking->id,
            'wish_id'         => $booking->wish_id,
            'reporter_id'     => $me->id,
            'accused_user_id' => $booking->user_id,
            'accused_token'   => $booking->token,
            'accused_name'    => $booking->name,
            'wish_title'      => $booking->wish->title,
            'reason'          => $validated['reason'],
            'comment'         => $validated['comment'] ?? null,
        ]);

        return back()->with('success', 'Жалоба отправлена. Ответ администратора придёт в раздел «Сообщения».');
    }
}
