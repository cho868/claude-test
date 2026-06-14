<?php

namespace App\Http\Controllers;

use App\Models\SleepRecord;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SleepRecordController extends Controller
{
    public function index()
    {
        $records = SleepRecord::where('user_id', auth()->id())
            ->orderByDesc('sleep_date')
            ->take(30)
            ->get();

        $avgMinutes = (int) round($records->avg('duration_minutes') ?? 0);

        // 直近7日分のグラフ用データ(古い順)
        $chart = $records->take(7)->reverse()->values()->map(fn ($r) => [
            'date' => $r->sleep_date->format('m/d'),
            'hours' => round($r->duration_minutes / 60, 1),
        ]);

        return view('sleep.index', [
            'records' => $records,
            'avgMinutes' => $avgMinutes,
            'chart' => $chart,
        ]);
    }

    public function store(Request $request, PointService $points)
    {
        $validated = $request->validate([
            'sleep_date' => ['required', 'date'],
            'bed_at' => ['required', 'date'],
            'wake_at' => ['required', 'date', 'after:bed_at'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $bedAt = Carbon::parse($validated['bed_at']);
        $wakeAt = Carbon::parse($validated['wake_at']);
        $duration = $bedAt->diffInMinutes($wakeAt);

        $record = SleepRecord::updateOrCreate(
            ['user_id' => $request->user()->id, 'sleep_date' => $validated['sleep_date']],
            [
                'bed_at' => $bedAt,
                'wake_at' => $wakeAt,
                'duration_minutes' => $duration,
                'note' => $validated['note'] ?? null,
            ],
        );

        // その日の初回記録のみポイント付与
        if ($record->wasRecentlyCreated) {
            $points->award($request->user(), 5, 'sleep_log', '睡眠記録');
        }

        return back()->with('status', '睡眠を記録しました(' . $record->hoursLabel() . ')');
    }

    public function destroy(SleepRecord $sleep)
    {
        abort_unless($sleep->user_id === auth()->id(), 403);
        $sleep->delete();

        return back()->with('status', '記録を削除しました。');
    }
}
