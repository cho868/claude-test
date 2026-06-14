<?php

namespace App\Http\Controllers;

use App\Models\EventAttendance;
use App\Models\ScheduleEvent;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ScheduleEventController extends Controller
{
    public function index()
    {
        $upcoming = ScheduleEvent::with(['user', 'attendances.user'])
            ->where('starts_at', '>=', Carbon::now()->startOfDay())
            ->orderBy('starts_at')
            ->get();

        $past = ScheduleEvent::with('user')
            ->where('starts_at', '<', Carbon::now()->startOfDay())
            ->orderByDesc('starts_at')
            ->take(10)
            ->get();

        return view('schedule.index', compact('upcoming', 'past'));
    }

    public function create()
    {
        return view('schedule.create');
    }

    public function store(Request $request, PointService $points)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $event = $request->user()->scheduleEvents()->create($validated);

        $points->award($request->user(), 10, 'create_event', "予定「{$event->title}」作成");

        return redirect()->route('schedule.show', $event)
            ->with('status', '予定を作成しました(+10pt)');
    }

    public function show(ScheduleEvent $schedule)
    {
        $schedule->load(['user', 'attendances.user']);

        $myAttendance = $schedule->attendances
            ->firstWhere('user_id', auth()->id());

        return view('schedule.show', ['event' => $schedule, 'myAttendance' => $myAttendance]);
    }

    public function attend(Request $request, ScheduleEvent $schedule)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:yes,no,maybe'],
            'comment' => ['nullable', 'string', 'max:255'],
        ]);

        EventAttendance::updateOrCreate(
            ['schedule_event_id' => $schedule->id, 'user_id' => auth()->id()],
            $validated,
        );

        return back()->with('status', '出欠を登録しました。');
    }

    public function destroy(ScheduleEvent $schedule)
    {
        abort_unless($schedule->user_id === auth()->id() || auth()->user()->is_admin, 403);
        $schedule->delete();

        return redirect()->route('schedule.index')->with('status', '削除しました。');
    }
}
