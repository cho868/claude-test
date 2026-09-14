<?php

namespace App\Http\Controllers;

use App\Models\Diary;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class DiaryController extends Controller
{
    /** 日記を書いた日の1日1回だけ付くポイント */
    public const DIARY_POINTS = 10;

    /**
     * 自分の日記（月別カレンダー + その月の一覧）。
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $month = $this->month($request);
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $entries = Diary::where('user_id', $user->id)
            ->whereBetween('entry_date', [$start, $end])
            ->orderByDesc('entry_date')->orderByDesc('id')
            ->get();

        // カレンダー用: 日付(Y-m-d) => その日の最初の日記
        $byDate = $entries->sortBy('entry_date')->keyBy(fn ($d) => $d->entry_date->toDateString());

        return view('diaries.index', [
            'entries' => $entries,
            'byDate' => $byDate,
            'month' => $month,
            'calendar' => $this->calendar($start, $end),
            'streak' => $this->streak($user->id),
            'totalCount' => Diary::where('user_id', $user->id)->count(),
            'todayEntry' => $byDate->get(Carbon::today()->toDateString()),
        ]);
    }

    /**
     * みんなの日記（共有されたものだけ）。
     */
    public function feed(Request $request)
    {
        $entries = Diary::with('user')
            ->where('visibility', 'members')
            ->orderByDesc('entry_date')->orderByDesc('id')
            ->paginate(15);

        return view('diaries.feed', compact('entries'));
    }

    public function create(Request $request)
    {
        $diary = new Diary([
            'entry_date' => $this->requestedDate($request),
            'visibility' => 'private',
        ]);

        return view('diaries.form', ['diary' => $diary]);
    }

    public function store(Request $request, PointService $points)
    {
        $data = $this->validateData($request);

        $diary = $request->user()->diaries()->create($data);

        // 習慣づけのため 1 日 1 回だけポイント
        $awarded = false;
        if (! $request->user()->pointLogs()->where('reason', 'diary')->whereDate('created_at', today())->exists()) {
            $points->award($request->user(), self::DIARY_POINTS, 'diary', '日記を書いた');
            $awarded = true;
        }

        return redirect()->route('diaries.show', $diary)
            ->with('status', '日記を保存しました。'.($awarded ? '(+'.self::DIARY_POINTS.'pt)' : ''));
    }

    public function show(Request $request, Diary $diary)
    {
        abort_unless($diary->canBeViewedBy($request->user()), 403);

        $diary->load('user');

        // 同じ人の前後の日記（自分のもの or 共有されたものだけ）
        $neighbors = fn (string $op, string $dir) => Diary::where('user_id', $diary->user_id)
            ->visibleTo($request->user())
            ->where('entry_date', $op, $diary->entry_date)
            ->orderBy('entry_date', $dir)
            ->first();

        return view('diaries.show', [
            'diary' => $diary,
            'prev' => $neighbors('<', 'desc'),
            'next' => $neighbors('>', 'asc'),
        ]);
    }

    public function edit(Request $request, Diary $diary)
    {
        abort_unless($diary->canBeEditedBy($request->user()), 403);

        return view('diaries.form', compact('diary'));
    }

    public function update(Request $request, Diary $diary)
    {
        abort_unless($diary->canBeEditedBy($request->user()), 403);

        $diary->update($this->validateData($request));

        return redirect()->route('diaries.show', $diary)->with('status', '日記を更新しました。');
    }

    public function destroy(Request $request, Diary $diary)
    {
        abort_unless($diary->canBeEditedBy($request->user()), 403);

        $diary->delete();

        return redirect()->route('diaries.index')->with('status', '日記を削除しました。');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:20000'],
            'mood' => ['nullable', Rule::in(array_keys(Diary::MOODS))],
            'weather' => ['nullable', Rule::in(array_keys(Diary::WEATHERS))],
            'visibility' => ['required', Rule::in(['private', 'members'])],
        ], [], [
            'entry_date' => '日付',
            'body' => '本文',
        ]);
    }

    /** 今日から遡って何日続けて書いているか（今日未記入なら昨日から数える） */
    private function streak(int $userId): int
    {
        $dates = Diary::where('user_id', $userId)
            ->where('entry_date', '>=', Carbon::today()->subYear())
            ->orderByDesc('entry_date')
            ->pluck('entry_date')
            ->map(fn ($d) => $d->toDateString())
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            return 0;
        }

        $cursor = Carbon::today();
        if ($dates->first() !== $cursor->toDateString()) {
            $cursor->subDay();
            if ($dates->first() !== $cursor->toDateString()) {
                return 0; // 今日も昨日も書いていない
            }
        }

        $streak = 0;
        foreach ($dates as $date) {
            if ($date !== $cursor->toDateString()) {
                break;
            }
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }

    /**
     * 月カレンダーのマス目（日曜始まり・前後月の空きを null で埋める）。
     *
     * @return array<int, array<int, \Illuminate\Support\Carbon|null>>
     */
    private function calendar(Carbon $start, Carbon $end): array
    {
        $cells = array_fill(0, (int) $start->dayOfWeek, null);

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $cells[] = $d->copy();
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return array_chunk($cells, 7);
    }

    private function month(Request $request): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m', (string) $request->query('m'))->startOfMonth();
        } catch (\Throwable $e) {
            return Carbon::now()->startOfMonth();
        }
    }

    private function requestedDate(Request $request): Carbon
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d', (string) $request->query('date'))->startOfDay();

            return $date->isFuture() ? Carbon::today() : $date;
        } catch (\Throwable $e) {
            return Carbon::today();
        }
    }
}
