<?php

namespace App\Http\Controllers;

use App\Models\ScheduleEvent;
use App\Models\Survey;
use App\Models\Title;
use App\Models\User;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // ランキング(ポイント上位)
        $ranking = User::with('title')
            ->orderByDesc('points')
            ->take(10)
            ->get();

        $nextTitle = $user->nextTitle();
        $currentTitle = $user->currentTitle();

        // 称号の進捗(現在の称号 → 次の称号 までの割合)
        $progress = 100;
        if ($nextTitle) {
            $base = $currentTitle?->required_points ?? 0;
            $span = max(1, $nextTitle->required_points - $base);
            $progress = (int) min(100, round(($user->points - $base) / $span * 100));
        }

        $upcomingEvents = ScheduleEvent::with('user')
            ->where('starts_at', '>=', Carbon::now())
            ->orderBy('starts_at')
            ->take(5)
            ->get();

        $openSurveys = Survey::withCount('votes')
            ->where('is_closed', false)
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard', [
            'user' => $user,
            'ranking' => $ranking,
            'currentTitle' => $currentTitle,
            'nextTitle' => $nextTitle,
            'progress' => $progress,
            'titles' => Title::orderBy('required_points')->get(),
            'upcomingEvents' => $upcomingEvents,
            'openSurveys' => $openSurveys,
            'recentPointLogs' => $user->pointLogs()->take(8)->get(),
        ]);
    }
}
