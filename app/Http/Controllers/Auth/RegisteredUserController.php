<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PointService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class RegisteredUserController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(Request $request, PointService $points)
    {
        // 招待コードが設定されている場合は一致を必須にする(身内以外の登録を防ぐ)
        $inviteCode = config('portal.invite_code');
        if (! empty($inviteCode)) {
            $request->validate(
                ['invite_code' => ['required', 'string']],
                ['invite_code.required' => '招待コードを入力してください。'],
            );

            if (! hash_equals((string) $inviteCode, (string) $request->input('invite_code'))) {
                return back()
                    ->withInput($request->except('password', 'password_confirmation'))
                    ->withErrors(['invite_code' => '招待コードが正しくありません。']);
            }
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create($validated);

        // 管理者権限は自動付与しない。最初の管理者は
        //   php artisan portal:make-admin {email}
        // で任命する（既存の管理者は管理画面のユーザー管理から付与/剥奪できる）。

        event(new Registered($user));
        Auth::login($user);

        $points->awardDailyLogin($user);

        return redirect()->route('dashboard')
            ->with('status', 'ようこそ！ようこそポータルへ。登録ボーナスを付与しました。');
    }
}
