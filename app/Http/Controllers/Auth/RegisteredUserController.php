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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create($validated);

        // 最初のユーザーは管理者にしておく(身内サイトの初期セットアップ用)
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
