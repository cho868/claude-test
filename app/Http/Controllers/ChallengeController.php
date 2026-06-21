<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Services\PointService;
use Illuminate\Http\Request;

class ChallengeController extends Controller
{
    public function index()
    {
        $challenges = Challenge::with('participants')
            ->withCount('participants')
            ->orderByDesc('starts_on')
            ->paginate(12);

        return view('challenges.index', compact('challenges'));
    }

    public function create()
    {
        return view('challenges.create');
    }

    public function store(Request $request, PointService $points)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'metric' => ['required', 'in:weight_loss,exercise_minutes'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);

        $challenge = $request->user()->challenges()->create($data);
        $challenge->participants()->attach($request->user()->id); // 作成者は自動参加

        $points->award($request->user(), 10, 'create_challenge', "チャレンジ「{$challenge->title}」作成");

        return redirect()->route('challenges.show', $challenge)
            ->with('status', 'チャレンジを作成しました(+10pt)');
    }

    public function show(Challenge $challenge)
    {
        $challenge->load('user', 'participants');

        return view('challenges.show', [
            'challenge' => $challenge,
            'standings' => $challenge->standings(),
            'joined' => $challenge->participants->contains(auth()->id()),
        ]);
    }

    public function join(Challenge $challenge)
    {
        $challenge->participants()->syncWithoutDetaching([auth()->id()]);

        return back()->with('status', 'チャレンジに参加しました！');
    }

    public function leave(Challenge $challenge)
    {
        $challenge->participants()->detach(auth()->id());

        return back()->with('status', 'チャレンジから抜けました。');
    }

    public function destroy(Challenge $challenge)
    {
        abort_unless($challenge->user_id === auth()->id() || auth()->user()->is_admin, 403);
        $challenge->delete();

        return redirect()->route('challenges.index')->with('status', '削除しました。');
    }
}
