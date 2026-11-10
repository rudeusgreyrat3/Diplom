<?php

namespace App\Http\Middleware;

use App\Models\Ban;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Если пользователя заблокировали, пока он был залогинен,
 * при следующем же запросе выкидываем его из аккаунта.
 */
class LogoutBannedUser
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && ($ban = Ban::activeForUser(Auth::id()))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Аккаунт заблокирован ' . $ban->untilText() . '.'
                    . ($ban->reason ? ' Причина: ' . $ban->reason : ''),
            ]);
        }

        return $next($request);
    }
}
