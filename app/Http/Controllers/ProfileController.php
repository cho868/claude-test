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

        // Steam ID は 64bit / バニティ名 / プロフィールURL のいずれでも受け付けて解決する
        if (! empty($validated['steam_id'])) {
            $resolved = app(\App\Services\SteamService::class)->resolveSteamId($validated['steam_id']);
            if ($resolved) {
                $validated['steam_id'] = $resolved;
            } else {
                return back()->withInput()->withErrors([
                    'steam_id' => 'Steam ID を解決できませんでした。17桁のID・バニティ名・プロフィールURLのいずれかを入力してください'
                        . (\App\Services\SteamService::isConfigured() ? '（バニティ名はプロフィール公開が必要）。' : '（バニティ名の解決にはサーバーのSTEAM_API_KEYが必要）。'),
                ]);
            }
        }

        $user->update($validated);

        return back()->with('status', 'プロフィールを更新しました。'
            . (! empty($validated['steam_id']) ? '（Steam ID: ' . $validated['steam_id'] . '）' : ''));
    }
}
