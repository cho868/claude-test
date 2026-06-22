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
            ->orderBy('bed_at')
            ->take(120)
            ->get();

        // 日ごとに合算（分割睡眠を1日として集計）
        $days = $records->groupBy(fn ($r) => $r->sleep_date->format('Y-m-d'))
            ->map(fn ($segs) => [
                'date' => $segs->first()->sleep_date,
                'total' => (int) $segs->sum('duration_minutes'),
                'segments' => $segs,
            ])
            ->values();

        $avgMinutes = (int) round($days->avg('total') ?? 0);

        // 直近7日分のグラフ用データ(古い順・1日の合計)
        $chart = $days->take(7)->reverse()->values()->map(fn ($d) => [
            'date' => $d['date']->format('m/d'),
            'hours' => round($d['total'] / 60, 1),
        ]);

        return view('sleep.index', [
            'days' => $days,
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

        // その日の最初の記録か（ポイントは1日1回だけ）
        $firstOfDay = ! SleepRecord::where('user_id', $request->user()->id)
            ->where('sleep_date', $validated['sleep_date'])->exists();

        $record = SleepRecord::create([
            'user_id' => $request->user()->id,
            'sleep_date' => $validated['sleep_date'],
            'bed_at' => $bedAt,
            'wake_at' => $wakeAt,
            'duration_minutes' => $bedAt->diffInMinutes($wakeAt),
            'note' => $validated['note'] ?? null,
        ]);

        if ($firstOfDay) {
            $points->award($request->user(), 5, 'sleep_log', '睡眠記録');
        }

        return back()->with('status', '睡眠を記録しました(' . $record->hoursLabel() . ')。同じ日にもう一度記録すれば分割睡眠も合算されます。');
    }

    public function destroy(SleepRecord $sleep)
    {
        abort_unless($sleep->user_id === auth()->id(), 403);
        $sleep->delete();

        return back()->with('status', '記録を削除しました。');
    }
}
