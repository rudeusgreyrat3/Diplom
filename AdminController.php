<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ban;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\UserMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    const DURATIONS = [
        '1'       => '1 день',
        '3'       => '3 дня',
        '7'       => '7 дней',
        '30'      => '30 дней',
        'forever' => 'Навсегда',
    ];

    // Список жалоб
    public function index(Request $request)
    {
        $status = $request->query('status', 'new');

        $query = Complaint::with(['reporter', 'accusedUser'])->latest();
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $complaints = $query->paginate(30)->withQueryString();

        $counts = Complaint::select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');

        return view('admin.index', [
            'complaints'  => $complaints,
            'status'      => $status,
            'counts'      => $counts,
            'activeBans'  => Ban::active()->count(),
        ]);
    }

    // Карточка жалобы: кто пожаловался, на кого, что этот человек натворил
    public function show(Complaint $complaint)
    {
        $complaint->load(['reporter', 'accusedUser', 'messages']);

        $bookings = $complaint->accusedBookingsQuery()
            ->with('wish.wishlist.user')
            ->latest()
            ->get();

        $related = Complaint::sameAccused($complaint)
            ->with('reporter')
            ->latest()
            ->get();

        $stats = [
            'active'      => $bookings->count(),
            'last24h'     => $bookings->where('created_at', '>=', now()->subDay())->count(),
            'owners'      => $bookings->map(fn ($b) => $b->wish?->wishlist?->user_id)->filter()->unique()->count(),
            'names'       => $bookings->pluck('name')->filter()->unique()->values(),
            'complaints'  => $related->count(),
            'reporters'   => $related->pluck('reporter_id')->unique()->count(),
        ];

        // самый «плотный» владелец: сколько броней у одного человека
        $byOwner = $bookings->groupBy(fn ($b) => $b->wish?->wishlist?->user?->username)
            ->map->count()->sortDesc();

        $ban = $complaint->accused_user_id
            ? Ban::activeForUser($complaint->accused_user_id)
            : Ban::activeForToken($complaint->accused_token);

        $banHistory = Ban::query()
            ->when($complaint->accused_user_id,
                fn ($q) => $q->where('user_id', $complaint->accused_user_id),
                fn ($q) => $q->where('token', $complaint->accused_token))
            ->latest()->get();

        return view('admin.show', [
            'complaint'  => $complaint,
            'bookings'   => $bookings,
            'related'    => $related,
            'stats'      => $stats,
            'byOwner'    => $byOwner,
            'ban'        => $ban,
            'banHistory' => $banHistory,
            'durations'  => self::DURATIONS,
        ]);
    }

    // Заблокировать нарушителя
    public function ban(Request $request, Complaint $complaint)
    {
        $validated = $request->validate([
            'duration'         => ['required', Rule::in(array_keys(self::DURATIONS))],
            'reason'           => 'nullable|string|max:500',
            'release_bookings' => 'nullable|boolean',
            'notify_reporters' => 'nullable|boolean',
        ]);

        if (!$complaint->canIdentifyAccused()) {
            return back()->with('error', 'Эту бронь сделали до появления токенов — заблокировать некого. Можно только снять бронь.');
        }

        $until = $validated['duration'] === 'forever' ? null : now()->addDays((int) $validated['duration']);

        Ban::create([
            'user_id'      => $complaint->accused_user_id,
            // токен баним и для аккаунтов — чтобы нельзя было выйти и бронировать гостем с того же браузера
            'token'        => $complaint->accused_token,
            'display_name' => $complaint->accusedUser?->username ? '@' . $complaint->accusedUser->username : $complaint->accused_name,
            'reason'       => $validated['reason'] ?? null,
            'until'        => $until,
            'complaint_id' => $complaint->id,
        ]);

        $released = 0;
        if ($request->boolean('release_bookings')) {
            $released = $complaint->accusedBookingsQuery()->delete();
        }

        // все открытые жалобы на этого же человека считаем решёнными
        $openRelated = Complaint::sameAccused($complaint)->where('status', 'new')->get();
        Complaint::whereIn('id', $openRelated->pluck('id')->push($complaint->id)->all())
            ->update(['status' => 'resolved', 'resolved_at' => now()]);

        if ($request->boolean('notify_reporters')) {
            $untilText = $until ? 'до ' . $until->format('d.m.Y') : 'навсегда';
            $body = 'Спасибо за жалобу! Мы проверили пользователя «' . ($complaint->accused_name ?: 'без имени') . '» '
                . 'и заблокировали его ' . $untilText . '.'
                . ($released ? ' Все его брони сняты — подарки снова свободны.' : '');

            foreach ($openRelated->concat([$complaint])->unique('reporter_id') as $c) {
                UserMessage::create([
                    'user_id'      => $c->reporter_id,
                    'complaint_id' => $c->id,
                    'title'        => 'Пользователь успешно заблокирован',
                    'body'         => $body,
                ]);
            }
        }

        $msg = 'Нарушитель заблокирован ' . ($until ? 'до ' . $until->format('d.m.Y H:i') : 'навсегда') . '.';
        if ($released) {
            $msg .= ' Снято броней: ' . $released . '.';
        }

        return redirect()->route('admin.complaints.show', $complaint)->with('success', $msg);
    }

    // Снять все брони нарушителя без блокировки
    public function releaseBookings(Complaint $complaint)
    {
        $released = $complaint->accusedBookingsQuery()->delete();

        return redirect()->route('admin.complaints.show', $complaint)
            ->with('success', 'Снято броней: ' . $released . '. Подарки снова свободны.');
    }

    // Написать сообщение тому, кто пожаловался (+ по желанию сменить статус жалобы)
    public function message(Request $request, Complaint $complaint)
    {
        $validated = $request->validate([
            'title'  => 'required|string|max:255',
            'body'   => 'required|string|max:3000',
            'status' => ['nullable', Rule::in(['keep', 'resolved', 'rejected'])],
        ], [
            'body.required' => 'Напишите текст сообщения',
        ]);

        UserMessage::create([
            'user_id'      => $complaint->reporter_id,
            'complaint_id' => $complaint->id,
            'title'        => $validated['title'],
            'body'         => $validated['body'],
        ]);

        if (in_array($validated['status'] ?? 'keep', ['resolved', 'rejected'], true)) {
            $complaint->update(['status' => $validated['status'], 'resolved_at' => now()]);
        }

        return redirect()->route('admin.complaints.show', $complaint)->with('success', 'Сообщение отправлено.');
    }

    // Отклонить жалобу без сообщения
    public function reject(Complaint $complaint)
    {
        $complaint->update(['status' => 'rejected', 'resolved_at' => now()]);

        return redirect()->route('admin.complaints.show', $complaint)->with('success', 'Жалоба отклонена.');
    }

    // Список блокировок
    public function bans()
    {
        $bans = Ban::with(['user', 'complaint'])->latest()->paginate(30);

        return view('admin.bans', compact('bans'));
    }

    public function liftBan(Ban $ban)
    {
        $ban->update(['lifted_at' => now()]);

        return back()->with('success', 'Блокировка снята.');
    }
}
