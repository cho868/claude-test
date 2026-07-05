<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request, PointService $points)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);
        $credentials['username'] = strtolower($credentials['username']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'ログインIDかパスワードが正しくありません。']);
        }

        $request->session()->regenerate();

        $earned = $points->awardDailyLogin($request->user());

        return redirect()->intended(route('dashboard'))->with(
            'status',
            $earned > 0
                ? "ログインボーナス +{$earned}pt を獲得しました！"
                : 'おかえりなさい！',
        );
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
