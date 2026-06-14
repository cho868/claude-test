<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\PointService;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    public function index()
    {
        $tournaments = Tournament::with('user')->latest()->paginate(12);

        return view('tournaments.index', compact('tournaments'));
    }

    public function create()
    {
        return view('tournaments.create');
    }

    public function store(Request $request, PointService $points)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'format' => ['required', 'in:single,double'],
            'description' => ['nullable', 'string'],
            'participants_text' => ['required', 'string'],
        ]);

        $participants = $this->parseParticipants($validated['participants_text']);

        abort_if(count($participants) < 2, 422, '参加者は2人以上必要です。');

        $tournament = Tournament::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'format' => $validated['format'],
            'description' => $validated['description'] ?? null,
            'participants' => $participants,
            'bracket' => $this->buildBracket($participants),
            'status' => 'ongoing',
        ]);

        $points->award($request->user(), 15, 'create_tournament', "トーナメント「{$tournament->name}」作成");

        return redirect()->route('tournaments.show', $tournament)
            ->with('status', 'トーナメントを作成しました(+15pt)');
    }

    public function show(Tournament $tournament)
    {
        $tournament->load('user');

        return view('tournaments.show', compact('tournament'));
    }

    /**
     * 勝者の記録を保存(対戦表の matches を上書き)。
     */
    public function update(Request $request, Tournament $tournament)
    {
        $this->authorizeOwner($tournament);

        $validated = $request->validate([
            'bracket' => ['required', 'json'],
            'status' => ['nullable', 'in:ongoing,finished'],
        ]);

        $tournament->update([
            'bracket' => json_decode($validated['bracket'], true),
            'status' => $validated['status'] ?? $tournament->status,
        ]);

        return back()->with('status', '対戦表を更新しました。');
    }

    public function destroy(Tournament $tournament)
    {
        $this->authorizeOwner($tournament);
        $tournament->delete();

        return redirect()->route('tournaments.index')->with('status', '削除しました。');
    }

    private function parseParticipants(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * シングルイリミネーション用の1回戦組み合わせを生成する。
     * 2のべき乗になるよう不戦勝(BYE)を補う。
     */
    private function buildBracket(array $participants): array
    {
        shuffle($participants);
        $size = 1;
        while ($size < count($participants)) {
            $size *= 2;
        }

        $slots = $participants;
        while (count($slots) < $size) {
            $slots[] = null; // BYE
        }

        $round = [];
        for ($i = 0; $i < $size; $i += 2) {
            $round[] = [
                'p1' => $slots[$i],
                'p2' => $slots[$i + 1],
                'winner' => null,
            ];
        }

        return [
            'rounds' => [$round],
            'size' => $size,
        ];
    }

    private function authorizeOwner(Tournament $tournament): void
    {
        abort_unless(
            $tournament->user_id === auth()->id() || auth()->user()->is_admin,
            403,
        );
    }
}
