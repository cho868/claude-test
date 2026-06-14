<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('profile.edit', [
            'user' => auth()->user(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'avatar' => ['nullable', 'url', 'max:500'],
            'discord_id' => ['nullable', 'string', 'max:100'],
            'steam_id' => ['nullable', 'string', 'max:100'],
        ]);

        $user->update($validated);

        return back()->with('status', 'プロフィールを更新しました。');
    }
}
