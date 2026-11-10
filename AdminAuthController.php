<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    public function loginForm(Request $request)
    {
        if ($request->session()->get('is_admin')) {
            return redirect()->route('admin.index');
        }

        return view('admin.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        $okLogin    = hash_equals((string) config('admin.login'), (string) $request->login);
        $okPassword = hash_equals((string) config('admin.password'), (string) $request->password);

        if (!$okLogin || !$okPassword) {
            return back()->withInput($request->only('login'))->withErrors(['login' => 'Неверный логин или пароль']);
        }

        $request->session()->regenerate();
        $request->session()->put('is_admin', true);

        return redirect()->route('admin.index');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('is_admin');

        return redirect()->route('admin.login');
    }
}
