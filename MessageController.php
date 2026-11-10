<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function index()
    {
        $messages = Auth::user()->messages()->latest()->get();

        // запоминаем, какие были непрочитанными, чтобы подсветить их на странице,
        // и сразу помечаем прочитанными
        $unreadIds = $messages->whereNull('read_at')->pluck('id')->all();
        if ($unreadIds) {
            Auth::user()->messages()->whereIn('id', $unreadIds)->update(['read_at' => now()]);
        }

        return view('messages.index', compact('messages', 'unreadIds'));
    }
}
