<?php

namespace App\Services;

use App\Models\Monster;
use App\Models\PointLog;
use App\Models\RaidBoss;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * モンスターの能力値・進化・バトル・共有ボスを司る。
 *
 * 設計方針（SDカードへの書き込みを最小化）:
 *  - 能力値は保存しない。ユーザーの points / total_logins / login_streak から都度算出する。
 *    → 「ポータルを使うほど強くなる」が自然に成立し、更新のための書き込みが一切発生しない。
 *  - バトルはシード値から決定論的に再現できるので、戦闘ログを保存しない。
 */
class MonsterService
{
    /** 種族ごとの補正と見た目。合計補正は概ね同等にしてバランスを取る。 */
    public const SPECIES = [
        'slime'  => ['name' => 'スライム', 'emoji' => '🫧', 'hp' => 14, 'atk' => -1, 'def' => 2,  'spd' => -1],
        'dragon' => ['name' => 'ドラゴン', 'emoji' => '🐲', 'hp' => 0,  'atk' => 5,  'def' => 1,  'spd' => 0],
        'beast'  => ['name' => 'けもの',   'emoji' => '🐺', 'hp' => 4,  'atk' => 2,  'def' => -1, 'spd' => 3],
        'bird'   => ['name' => 'とり',     'emoji' => '🦅', 'hp' => -6, 'atk' => 2,  'def' => -1, 'spd' => 6],
        'golem'  => ['name' => 'ゴーレム', 'emoji' => '🗿', 'hp' => 10, 'atk' => 1,  'def' => 6,  'spd' => -4],
        'ghost'  => ['name' => 'ゴースト', 'emoji' => '👻', 'hp' => -8, 'atk' => 6,  'def' => 0,  'spd' => 3],
    ];

    /** レベル: ポイントから算出（序盤は伸びやすく、後半は緩やか） */
    public function level(User $user): int
    {
        return max(1, (int) floor(sqrt(max(0, (int) $user->points) / 20)) + 1);
    }

    /** 進化段階 1〜4 */
    public function stage(int $level): int
    {
        return $level >= 25 ? 4 : ($level >= 12 ? 3 : ($level >= 5 ? 2 : 1));
    }

    /** 次のレベルに必要なポイント（進捗バー用） */
    public function nextLevelPoints(int $level): int
    {
        return ($level ** 2) * 20;
    }

    /**
     * 能力値を算出（保存しない）。
     * ログイン継続・活動量がそのまま強さになる。
     */
    public function stats(User $user, ?Monster $monster = null): array
    {
        $monster ??= Monster::where('user_id', $user->id)->first();
        $sp = self::SPECIES[$monster?->species ?? 'slime'] ?? self::SPECIES['slime'];

        $lv = $this->level($user);
        $logins = (int) $user->total_logins;
        $streak = (int) $user->login_streak;

        return [
            'level' => $lv,
            'stage' => $this->stage($lv),
            'hp'  => max(1, 40 + $lv * 8 + min($logins, 200) + $sp['hp']),
            'atk' => max(1, 8 + $lv * 3 + $sp['atk']),
            'def' => max(0, 5 + $lv * 2 + $sp['def']),
            'spd' => max(1, 6 + $lv * 2 + min($streak, 30) + $sp['spd']),
            'species' => $monster?->species ?? 'slime',
            'speciesName' => $sp['name'],
            'emoji' => $sp['emoji'],
            'name' => $monster?->name ?? '（未作成）',
        ];
    }

    /**
     * バトルを決定論的にシミュレートする。
     * 同じ seed と同じ能力値なら必ず同じ結果になるため、戦闘ログを保存する必要がない。
     *
     * @return array{log: array, winner: ?string, turns: int}
     */
    public function simulate(array $a, array $b, int $seed): array
    {
        mt_srand($seed);

        $hpA = $a['hp'];
        $hpB = $b['hp'];
        $log = [];
        $turn = 0;

        // 素早さ順。同速はシードで決める。
        $aFirst = $a['spd'] === $b['spd'] ? (mt_rand(0, 1) === 1) : ($a['spd'] > $b['spd']);

        while ($hpA > 0 && $hpB > 0 && $turn < 30) {
            $turn++;
            $order = $aFirst ? [['a', 'b'], ['b', 'a']] : [['b', 'a'], ['a', 'b']];

            foreach ($order as [$atkKey, $defKey]) {
                if ($hpA <= 0 || $hpB <= 0) {
                    break;
                }
                $atkr = $atkKey === 'a' ? $a : $b;
                $defr = $defKey === 'a' ? $a : $b;

                // 命中判定（素早さ差でわずかに回避）
                $evade = max(2, min(20, 10 + (int) (($defr['spd'] - $atkr['spd']) / 3)));
                if (mt_rand(1, 100) <= $evade) {
                    $log[] = ['t' => $turn, 'by' => $atkKey, 'miss' => true, 'dmg' => 0, 'hpA' => $hpA, 'hpB' => $hpB];
                    continue;
                }

                $crit = mt_rand(1, 100) <= 8;
                $base = $atkr['atk'] * mt_rand(85, 115) / 100;
                $dmg = (int) max(1, round(($base * ($crit ? 1.8 : 1)) - $defr['def'] * 0.45));

                if ($defKey === 'a') {
                    $hpA -= $dmg;
                } else {
                    $hpB -= $dmg;
                }

                $log[] = [
                    't' => $turn, 'by' => $atkKey, 'miss' => false, 'crit' => $crit, 'dmg' => $dmg,
                    'hpA' => max(0, $hpA), 'hpB' => max(0, $hpB),
                ];
            }
        }

        $winner = null;
        if ($hpA <= 0 && $hpB > 0) {
            $winner = 'b';
        } elseif ($hpB <= 0 && $hpA > 0) {
            $winner = 'a';
        } elseif ($hpA !== $hpB) {
            $winner = $hpA > $hpB ? 'a' : 'b';   // 時間切れはHP残量で判定
        }

        return ['log' => $log, 'winner' => $winner, 'turns' => $turn];
    }

    // ===== みんなの共有ボス（週替わり） =====

    public function currentWeek(): string
    {
        return now()->format('o-\WW');
    }

    /** 今週のボスを取得（無ければ生成）。週に1回だけ書き込みが発生する。 */
    public function currentBoss(): RaidBoss
    {
        $week = $this->currentWeek();

        return RaidBoss::firstOrCreate(
            ['week' => $week],
            $this->makeBossFor($week),
        );
    }

    private function makeBossFor(string $week): array
    {
        $names = ['グルーミィ', 'ヴォルガノス', 'ネビュラ', 'ガイアクラブ', 'シャドウメア', 'テンペスト'];
        $species = array_keys(self::SPECIES);
        $i = (int) substr($week, -2);

        // 参加人数に応じて目標を決める（1人あたり週700ptが目安）
        $members = max(1, User::count());

        return [
            'name' => $names[$i % count($names)],
            'species' => $species[$i % count($species)],
            'total_hp' => 1500 + $members * 700,
        ];
    }

    /**
     * 今週の与ダメージ = 今週みんなが獲得したポイント合計。
     * point_logs を集計するだけなので、進捗を保存する必要がない。
     */
    public function raidProgress(RaidBoss $boss): array
    {
        $start = Carbon::now()->startOfWeek();

        $rows = PointLog::where('created_at', '>=', $start)
            ->selectRaw('user_id, SUM(amount) as dmg')
            ->groupBy('user_id')
            ->orderByDesc('dmg')
            ->get();

        $damage = (int) $rows->sum('dmg');
        $users = User::whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        return [
            'damage' => $damage,
            'remaining' => max(0, $boss->total_hp - $damage),
            'percent' => min(100, (int) round($damage / max(1, $boss->total_hp) * 100)),
            'defeated' => $damage >= $boss->total_hp,
            'contributors' => $rows->map(fn ($r) => [
                'user' => $users[$r->user_id] ?? null,
                'damage' => (int) $r->dmg,
            ])->filter(fn ($r) => $r['user'])->values(),
        ];
    }
}
