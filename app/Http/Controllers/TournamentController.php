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
            'bracket' => $this->buildBracket($participants, $validated['format']),
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
     * トーナメントを「試合グラフ」として生成する。
     * 各試合は勝者の行き先(winnerTo)と敗者の行き先(loserTo)を持ち、
     * シングルもダブルも同じ仕組みで扱える。表示側は seed(W round0) と各試合の
     * pick(選んだ勝者) から全スロットを導出する。
     *
     * matches[*] = id, bracket('W'|'L'|'GF'), round, col, p1, p2, pick, winner,
     *              winnerTo[id,slot]|null, loserTo[id,slot]|null, label
     */
    private function buildBracket(array $participants, string $format): array
    {
        shuffle($participants);
        $n = count($participants);
        $size = 1;
        while ($size < $n) {
            $size *= 2;
        }

        // 標準シード配置（BYEを上位シードへ分散）
        $order = $this->seedOrder($size);
        $slots = [];
        foreach ($order as $seed) {
            $slots[] = $seed <= $n ? $participants[$seed - 1] : null;
        }

        $rounds = (int) log($size, 2); // 勝者側(WB)のラウンド数
        $matches = [];
        $add = function (string $id, string $bracket, int $round, int $col, string $label) use (&$matches) {
            $matches[$id] = [
                'id' => $id, 'bracket' => $bracket, 'round' => $round, 'col' => $col,
                'p1' => null, 'p2' => null, 'pick' => null, 'winner' => null,
                'winnerTo' => null, 'loserTo' => null, 'label' => $label,
            ];
        };

        // --- 勝者側(WB) ---
        for ($r = 0; $r < $rounds; $r++) {
            $count = intdiv($size, 1 << ($r + 1));
            for ($c = 0; $c < $count; $c++) {
                $label = $r === $rounds - 1
                    ? ($format === 'double' ? 'WB決勝' : '決勝')
                    : 'WB R' . ($r + 1);
                $add("W{$r}_{$c}", 'W', $r, $c, $label);
            }
        }
        for ($c = 0; $c < intdiv($size, 2); $c++) {
            $matches["W0_{$c}"]['p1'] = $slots[2 * $c];
            $matches["W0_{$c}"]['p2'] = $slots[2 * $c + 1];
        }
        for ($r = 0; $r < $rounds - 1; $r++) {
            $count = intdiv($size, 1 << ($r + 1));
            for ($c = 0; $c < $count; $c++) {
                $matches["W{$r}_{$c}"]['winnerTo'] = ['W' . ($r + 1) . '_' . intdiv($c, 2), $c % 2 === 0 ? 'p1' : 'p2'];
            }
        }

        if ($format !== 'double') {
            return ['format' => 'single', 'size' => $size, 'matches' => array_values($matches)];
        }

        // --- ダブルイリミネーション ---
        // ※ 導出を1パスで行うため GF は最後に追加する（依存先より後ろに並べる）
        $matches['W' . ($rounds - 1) . '_0']['winnerTo'] = ['GF', 'p1'];

        if ($rounds === 1) {
            // 2人: 敗者がGFへ（敗者復活の1本勝負）
            $matches['W0_0']['loserTo'] = ['GF', 'p2'];
            $add('GF', 'GF', 0, 0, 'グランドファイナル');

            return ['format' => 'double', 'size' => $size, 'matches' => array_values($matches)];
        }

        // 敗者側(LB)のラウンド数とラウンドごとの試合数
        $lbTotal = 2 * $rounds - 2;
        $counts = [intdiv($size, 4)];
        for ($l = 1; $l < $lbTotal; $l++) {
            $counts[$l] = ($l % 2 === 1) ? $counts[$l - 1] : intdiv($counts[$l - 1], 2);
        }
        for ($l = 0; $l < $lbTotal; $l++) {
            for ($c = 0; $c < $counts[$l]; $c++) {
                $label = $l === $lbTotal - 1 ? 'LB決勝' : 'LB R' . ($l + 1);
                $add("L{$l}_{$c}", 'L', $l, $c, $label);
            }
        }

        // WB敗者の落とし込み
        for ($c = 0; $c < intdiv($size, 2); $c++) {
            $matches["W0_{$c}"]['loserTo'] = ['L0_' . intdiv($c, 2), $c % 2 === 0 ? 'p1' : 'p2'];
        }
        for ($r = 1; $r < $rounds; $r++) {
            $count = intdiv($size, 1 << ($r + 1));
            $l = 2 * $r - 1; // この回の敗者が入るLBのメジャー round
            for ($c = 0; $c < $count; $c++) {
                $matches["W{$r}_{$c}"]['loserTo'] = ["L{$l}_{$c}", 'p2'];
            }
        }

        // LB勝者の行き先
        for ($l = 0; $l < $lbTotal; $l++) {
            for ($c = 0; $c < $counts[$l]; $c++) {
                if ($l === $lbTotal - 1) {
                    $matches["L{$l}_{$c}"]['winnerTo'] = ['GF', 'p2'];
                } elseif ($l % 2 === 0) {
                    // マイナー → 次のメジャーへ（同じ列のp1）
                    $matches["L{$l}_{$c}"]['winnerTo'] = ['L' . ($l + 1) . "_{$c}", 'p1'];
                } else {
                    // メジャー → 次のマイナーへ（2列ずつまとめる）
                    $matches["L{$l}_{$c}"]['winnerTo'] = ['L' . ($l + 1) . '_' . intdiv($c, 2), $c % 2 === 0 ? 'p1' : 'p2'];
                }
            }
        }

        // GF を最後に追加（導出の処理順を依存先より後ろにするため）
        $add('GF', 'GF', 0, 0, 'グランドファイナル');

        return ['format' => 'double', 'size' => $size, 'matches' => array_values($matches)];
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
