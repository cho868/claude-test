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
     * シングルイリミネーションの全ラウンドを生成する。
     * 標準シード配置でBYE(不戦勝)を均等に分散し、1回戦のBYEは自動で勝ち上がる。
     * これにより「人数が半端でも特定の人だけ決勝直行」が起きない。
     */
    private function buildBracket(array $participants): array
    {
        shuffle($participants);
        $n = count($participants);

        $size = 1;
        while ($size < $n) {
            $size *= 2;
        }

        // 標準シード順（1,16,8,9,4,13,... のような位置）にプレイヤーを配置。BYEは上位シードへ分散
        $order = $this->seedOrder($size);
        $slots = [];
        foreach ($order as $seed) {
            $slots[] = $seed <= $n ? $participants[$seed - 1] : null;
        }

        // 1回戦（BYEは自動勝ち上がり）
        $first = [];
        for ($i = 0; $i < $size; $i += 2) {
            $p1 = $slots[$i];
            $p2 = $slots[$i + 1];
            $winner = null;
            if ($p1 !== null && $p2 === null) {
                $winner = $p1;
            } elseif ($p1 === null && $p2 !== null) {
                $winner = $p2;
            }
            $first[] = ['p1' => $p1, 'p2' => $p2, 'winner' => $winner];
        }

        $rounds = [$first];

        // 2回戦以降は空の対戦枠（前ラウンドの勝者が入る）
        $prev = $first;
        while (count($prev) > 1) {
            $next = [];
            for ($i = 0; $i < count($prev); $i += 2) {
                $next[] = [
                    'p1' => $prev[$i]['winner'],
                    'p2' => $prev[$i + 1]['winner'],
                    'winner' => null,
                ];
            }
            $rounds[] = $next;
            $prev = $next;
        }

        return ['rounds' => $rounds, 'size' => $size];
    }

    /**
     * トーナメントの標準シード順を返す（長さ = $size）。
     * 例: size=4 → [1,4,2,3] / size=8 → [1,8,4,5,2,7,3,6]
     */
    private function seedOrder(int $size): array
    {
        $seeds = [1, 2];
        while (count($seeds) < $size) {
            $sum = count($seeds) * 2 + 1;
            $next = [];
            foreach ($seeds as $s) {
                $next[] = $s;
                $next[] = $sum - $s;
            }
            $seeds = $next;
        }

        return $seeds;
    }

    private function authorizeOwner(Tournament $tournament): void
    {
        abort_unless(
            $tournament->user_id === auth()->id() || auth()->user()->is_admin,
            403,
        );
    }
}
