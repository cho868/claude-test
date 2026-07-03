<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * パスワード再設定（メール無し運用）。
 * 管理者が管理画面で発行したリンクを本人が開き、自分で新パスワードを設定する。
 */
class PasswordResetController extends Controller
{
    public function create(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'lowercase', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = User::where('email', $validated['email'])->first();

        // ユーザー不明でもトークン不一致でも同じエラーにする(存在の探りを防ぐ)
        if (! $user || ! Password::broker()->tokenExists($user, $validated['token'])) {
            return back()->withErrors(['email' => 'リンクが無効か期限切れです。管理者にリンクの再発行を依頼してください。']);
        }

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();
        Password::broker()->deleteToken($user); // 使い捨て

        return redirect()->route('login')->with('status', 'パスワードを変更しました。新しいパスワードでログインしてください。');
    }
}
