<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;

class AuthController extends Controller
{
    // Показать форму регистрации
    public function registerForm()
    {
        return view('auth.register');
    }

    // Обработать регистрацию
    public function register(Request $request)
    {
        $request->validate([
    'name'     => 'required|string|max:255',
    'username' => 'required|string|max:255|unique:users',
    'email'    => 'required|email|unique:users',
    'password' => 'required|min:6|confirmed',
], [
    'name.required'      => 'Введите имя',
    'name.max'           => 'Имя слишком длинное',
    'username.required'  => 'Введите юзернейм',
    'username.unique'    => 'Этот юзернейм уже занят',
    'email.required'     => 'Введите email',
    'email.email'        => 'Некорректный email',
    'email.unique'       => 'Этот email уже занят',
    'password.required'  => 'Введите пароль',
    'password.min'       => 'Пароль должен быть не менее 6 символов',
    'password.confirmed' => 'Пароли не совпадают',
]);

        $user = User::create([
            'name'     => $request->name,
            'username' => $request->username,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    // Показать форму входа
    public function loginForm()
    {
        return view('auth.login');
    }

    // Обработать вход
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            // заблокированного пользователя сразу выпускаем обратно
            if ($ban = \App\Models\Ban::activeForUser(Auth::id())) {
                Auth::logout();
                return back()->withInput($request->only('email'))->withErrors([
                    'email' => 'Аккаунт заблокирован ' . $ban->untilText() . '.'
                        . ($ban->reason ? ' Причина: ' . $ban->reason : ''),
                ]);
            }

            $request->session()->regenerate();
            // если человека отправили на вход с какой-то страницы (например, чтобы пожаловаться) — вернём его туда
            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors(['email' => 'Неверный email или пароль']);
    }

    // Выход
    public function logout()
    {
        Auth::logout();
        return redirect()->route('login');
    }

// Форма "забыл пароль"
    public function forgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    // Отправка ссылки на почту
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'Введите email',
            'email.email'    => 'Некорректный email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'Ссылка для сброса пароля отправлена на почту');
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors(['email' => 'Ссылку уже отправляли недавно, подожди минуту и попробуй снова']);
        }

        return back()->withErrors(['email' => 'Пользователь с таким email не найден']);
    }

    // Форма нового пароля (по ссылке из письма)
    public function resetPasswordForm(Request $request, $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    // Сохранение нового пароля
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:6|confirmed',
        ], [
            'email.required'      => 'Введите email',
            'email.email'         => 'Некорректный email',
            'password.required'   => 'Введите пароль',
            'password.min'        => 'Пароль должен быть не менее 6 символов',
            'password.confirmed'  => 'Пароли не совпадают',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Пароль изменён, теперь можно войти')
            : back()->withErrors(['email' => 'Ссылка недействительна или устарела']);
    }

}