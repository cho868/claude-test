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

        // 最初に登録したユーザーだけ管理者にする(初期セットアップ用)。
        // 2人目以降は一般ユーザー。あとから管理画面 or
        //   php artisan portal:make-admin {email}
        // で付与/剥奪できる。
        if (User::count() === 1) {
            $user->forceFill(['is_admin' => true])->save();
        }

        event(new Registered($user));
        Auth::login($user);

        $points->awardDailyLogin($user);

        return redirect()->route('dashboard')
            ->with('status', 'ようこそ！ようこそポータルへ。登録ボーナスを付与しました。');
    }
}
