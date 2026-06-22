<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /** DiceBear で使える自動生成スタイル */
    public const DICEBEAR_VARIANTS = [
        'fun-emoji', 'bottts', 'thumbs', 'adventurer', 'avataaars',
        'big-smile', 'pixel-art', 'shapes', 'identicon', 'lorelei',
    ];

    public function edit()
    {
        return view('profile.edit', [
            'user' => auth()->user(),
            'variants' => self::DICEBEAR_VARIANTS,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'discord_id' => ['nullable', 'string', 'max:100'],
            'steam_id' => ['nullable', 'string', 'max:100'],
            'avatar_style' => ['required', 'in:emoji,dicebear'],
            'avatar_emoji' => ['nullable', 'string', 'max:8'],
            'avatar_color' => ['nullable', 'string', 'max:9'],
            'avatar_variant' => ['nullable', Rule::in(self::DICEBEAR_VARIANTS)],
            'avatar_seed' => ['nullable', 'string', 'max:60'],
        ]);

        $user->update($validated);

        return back()->with('status', 'プロフィールを更新しました。');
    }
}
