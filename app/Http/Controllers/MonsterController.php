<?php

namespace App\Http\Controllers;

use App\Models\Monster;
use App\Models\MonsterBattle;
use App\Models\User;
use App\Services\MonsterService;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonsterController extends Controller
{
    /** 1日に挑めるバトル数（書き込み量の抑制も兼ねる） */
    private const DAILY_BATTLE_LIMIT = 5;

    public function __construct(private MonsterService $svc) {}

    public function index()
    {
        $me = auth()->user();
        $mine = Monster::where('user_id', $me->id)->first();

        $others = Monster::with('user')
            ->where('user_id', '!=', $me->id)
            ->get()
            ->map(fn ($m) => [
                'user' => $m->user,
                'monster' => $m,
                'stats' => $this->svc->stats($m->user, $m),
            ])
            ->sortByDesc(fn ($r) => $r['stats']['level'])
            ->values();

        $boss = $this->svc->currentBoss();

        return view('monsters.index', [
            'mine' => $mine,
            'myStats' => $mine ? $this->svc->stats($me, $mine) : null,
            'nextLevelPoints' => $mine ? $this->svc->nextLevelPoints($this->svc->level($me)) : 0,
            'others' => $others,
            'species' => MonsterService::SPECIES,
            'boss' => $boss,
            'raid' => $this->svc->raidProgress($boss),
            'battlesLeft' => self::DAILY_BATTLE_LIMIT - $this->todayBattles($me),
            'recent' => MonsterBattle::with(['challenger', 'opponent', 'winner'])
                ->latest('id')->take(10)->get(),
        ]);
    }

    public function store(Request $request, PointService $points)
    {
        abort_if(Monster::where('user_id', $request->user()->id)->exists(), 409);

        $data = $request->validate([
            'species' => ['required', Rule::in(array_keys(MonsterService::SPECIES))],
            'name' => ['required', 'string', 'max:40'],
        ]);

        Monster::create($data + ['user_id' => $request->user()->id]);
        $points->award($request->user(), 10, 'monster', 'モンスターを迎えた');

        return redirect()->route('monsters.index')->with('status', "{$data['name']} を仲間にしました！");
    }

    /** 名前だけ変更可（種族は変えられない） */
    public function update(Request $request)
    {
        $monster = Monster::where('user_id', $request->user()->id)->firstOrFail();
        $monster->update($request->validate(['name' => ['required', 'string', 'max:40']]));

        return back()->with('status', '名前を変更しました。');
    }

    public function battle(Request $request, User $opponent)
    {
        $me = $request->user();
        abort_if($opponent->id === $me->id, 400, '自分とは戦えません');

        $mine = Monster::where('user_id', $me->id)->firstOrFail();
        $theirs = Monster::where('user_id', $opponent->id)->firstOrFail();

        if ($this->todayBattles($me) >= self::DAILY_BATTLE_LIMIT) {
            return response()->json(['ok' => false, 'message' => '今日のバトル回数の上限です（明日また挑戦できます）'], 429);
        }

        $a = $this->svc->stats($me, $mine);
        $b = $this->svc->stats($opponent, $theirs);
        $seed = random_int(1, 2_000_000_000);
        $result = $this->svc->simulate($a, $b, $seed);

        $winnerId = $result['winner'] === 'a' ? $me->id : ($result['winner'] === 'b' ? $opponent->id : null);

        // 保存するのはシードと勝敗だけ。戦闘ログはシードから再現できるので保存しない。
        MonsterBattle::create([
            'challenger_id' => $me->id,
            'opponent_id' => $opponent->id,
            'seed' => $seed,
            'winner_id' => $winnerId,
            'turns' => $result['turns'],
        ]);

        return response()->json([
            'ok' => true,
            'a' => $a, 'b' => $b,
            'aName' => $me->name, 'bName' => $opponent->name,
            'log' => $result['log'],
            'winner' => $result['winner'],
            'turns' => $result['turns'],
            'left' => self::DAILY_BATTLE_LIMIT - $this->todayBattles($me),
        ]);
    }

    private function todayBattles(User $user): int
    {
        return MonsterBattle::where('challenger_id', $user->id)
            ->whereDate('created_at', today())
            ->count();
    }
}
