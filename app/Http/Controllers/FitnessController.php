<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ExerciseRecord;
use App\Models\WeightRecord;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FitnessController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $weights = WeightRecord::where('user_id', $user->id)
            ->orderByDesc('recorded_on')->take(60)->get();

        // グラフ用（古い順・直近30件）
        $weightChart = $weights->take(30)->reverse()->values()->map(fn ($r) => [
            'date' => $r->recorded_on->format('n/j'),
            'kg' => (float) $r->weight_kg,
        ]);

        $exercises = ExerciseRecord::where('user_id', $user->id)
            ->orderByDesc('recorded_on')->take(30)->get();

        // 直近7日の運動時間
        $weekMinutes = (int) ExerciseRecord::where('user_id', $user->id)
            ->where('recorded_on', '>=', Carbon::today()->subDays(6))
            ->sum('minutes');

        // 種目別合計
        $byActivity = ExerciseRecord::where('user_id', $user->id)
            ->selectRaw('activity, SUM(minutes) as total')
            ->groupBy('activity')->orderByDesc('total')->get();

        // 参加中・募集中のチャレンジ
        $challenges = Challenge::with('participants')
            ->where('ends_on', '>=', Carbon::today()->subDays(0))
            ->orderBy('ends_on')->take(5)->get();

        return view('fitness.index', [
            'weights' => $weights,
            'weightChart' => $weightChart,
            'exercises' => $exercises,
            'weekMinutes' => $weekMinutes,
            'byActivity' => $byActivity,
            'challenges' => $challenges,
            'latestWeight' => $weights->first(),
        ]);
    }

    public function storeWeight(Request $request, PointService $points)
    {
        $data = $request->validate([
            'recorded_on' => ['required', 'date'],
            'weight_kg' => ['required', 'numeric', 'min:1', 'max:400'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $record = WeightRecord::updateOrCreate(
            ['user_id' => $request->user()->id, 'recorded_on' => $data['recorded_on']],
            ['weight_kg' => $data['weight_kg'], 'note' => $data['note'] ?? null],
        );

        if ($record->wasRecentlyCreated) {
            $points->award($request->user(), 3, 'weight_log', '体重記録');
        }

        return back()->with('status', '体重を記録しました。');
    }

    public function storeExercise(Request $request, PointService $points)
    {
        $data = $request->validate([
            'recorded_on' => ['required', 'date'],
            'activity' => ['required', 'string', 'max:50'],
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'calories' => ['nullable', 'integer', 'min:0', 'max:20000'],
        ]);

        $request->user()->exerciseRecords()->create($data);
        $points->award($request->user(), 3, 'exercise_log', '運動記録');

        return back()->with('status', '運動を記録しました(+3pt)');
    }

    public function destroyWeight(WeightRecord $weight)
    {
        abort_unless($weight->user_id === auth()->id(), 403);
        $weight->delete();

        return back()->with('status', '記録を削除しました。');
    }

    public function destroyExercise(ExerciseRecord $exercise)
    {
        abort_unless($exercise->user_id === auth()->id(), 403);
        $exercise->delete();

        return back()->with('status', '記録を削除しました。');
    }
}
