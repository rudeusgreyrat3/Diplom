<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'avatar'   => 'nullable|image|max:4096',
        ], [
            'name.required'     => 'Введите имя',
            'username.required' => 'Введите никнейм',
            'username.unique'   => 'Этот никнейм уже занят',
            'avatar.image'      => 'Файл должен быть изображением',
            'avatar.max'        => 'Файл слишком большой (макс. 4 МБ)',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($validated);

        return redirect()->route('dashboard')->with('success', 'Профиль обновлён!');
    }
}