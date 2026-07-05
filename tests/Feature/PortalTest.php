<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\Title;
use App\Models\User;
use App\Services\PointService;
use Database\Seeders\TitleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TitleSeeder::class);
    }

    public function test_registration_grants_login_bonus_and_streak(): void
    {
        $response = $this->post('/register', [
            'name' => 'たろう',
            'username' => 'taro',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));

        $user = User::firstWhere('username', 'taro');
        $this->assertSame(1, $user->login_streak);
        // 基礎10pt + ストリーク(1日 * 2) = 12pt
        $this->assertSame(12, (int) $user->points);
        $this->assertTrue($user->is_admin, '最初のユーザーは管理者になる');
    }

    public function test_daily_login_bonus_is_awarded_once_per_day(): void
    {
        $user = User::factory()->create(['points' => 0]);
        $service = app(PointService::class);

        $first = $service->awardDailyLogin($user);
        $second = $service->awardDailyLogin($user->refresh());

        $this->assertSame(12, $first);   // 初日: 10 + 2
        $this->assertSame(0, $second);   // 同日2回目は付与なし
        $this->assertSame(12, (int) $user->refresh()->points);
    }

    public function test_streak_breaks_when_a_day_is_skipped(): void
    {
        $user = User::factory()->create([
            'last_login_date' => Carbon::today()->subDays(3),
            'login_streak' => 5,
            'points' => 100,
        ]);

        app(PointService::class)->awardDailyLogin($user);

        $this->assertSame(1, $user->refresh()->login_streak, '間が空いたらストリークはリセット');
    }

    public function test_title_is_assigned_based_on_points(): void
    {
        $user = User::factory()->create(['points' => 0]);
        $service = app(PointService::class);

        $service->award($user, 160, 'test');

        $expected = Title::where('required_points', '<=', 160)
            ->orderByDesc('required_points')->first();

        $this->assertSame($expected->id, $user->refresh()->title_id);
        $this->assertSame('常連', $user->currentTitle()->name);
    }

    public function test_survey_voting_replaces_previous_choice_for_single_choice(): void
    {
        $owner = User::factory()->create();
        $survey = Survey::create([
            'user_id' => $owner->id,
            'title' => 'テスト',
            'multiple_choice' => false,
        ]);
        $a = $survey->options()->create(['label' => 'A', 'sort_order' => 0]);
        $b = $survey->options()->create(['label' => 'B', 'sort_order' => 1]);

        $voter = User::factory()->create();

        $this->actingAs($voter)->post(route('surveys.vote', $survey), ['options' => [$a->id]]);
        $this->actingAs($voter)->post(route('surveys.vote', $survey), ['options' => [$b->id]]);

        // 単一選択なので票は1つだけ(B)に置き換わる
        $this->assertSame(1, $survey->votes()->where('user_id', $voter->id)->count());
        $this->assertSame($b->id, $survey->votes()->where('user_id', $voter->id)->first()->survey_option_id);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_admin_area_is_restricted_to_admins(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create(['is_admin' => false]);

        $this->actingAs($member)->get('/admin')->assertForbidden();
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_admin_only_documents_are_hidden_from_members(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create(['is_admin' => false]);

        $doc = \App\Models\Document::create([
            'user_id' => $admin->id,
            'category' => 'サーバー',
            'title' => '機密手順',
            'body' => 'secret',
            'is_public' => false,
            'visibility' => 'admin',
        ]);

        // 一般ユーザーは一覧に出ず、直接アクセスも403
        $this->actingAs($member)->get(route('documents.index'))->assertDontSee('機密手順');
        $this->actingAs($member)->get(route('documents.show', $doc))->assertForbidden();

        // 管理者は閲覧可
        $this->actingAs($admin)->get(route('documents.show', $doc))->assertOk();
    }

    public function test_nine_player_tournament_bracket_is_fair(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $names = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I']; // 9人

        $this->actingAs($admin)->post(route('tournaments.store'), [
            'name' => 'テスト大会',
            'format' => 'single',
            'participants_text' => implode("\n", $names),
        ])->assertRedirect();

        $bracket = \App\Models\Tournament::latest('id')->first()->bracket;
        $this->assertSame('single', $bracket['format']);
        $this->assertSame(16, $bracket['size']);

        // 勝者側1回戦(WB round0)を取り出す
        $first = collect($bracket['matches'])->filter(fn ($m) => $m['bracket'] === 'W' && $m['round'] === 0);
        $this->assertCount(8, $first);

        // 公平性の核心: 1回戦に「BYE対BYE」が無い＝BYEが分散し、誰も2回戦をスキップしない
        foreach ($first as $m) {
            $this->assertFalse($m['p1'] === null && $m['p2'] === null, '1回戦にBYE同士の組があってはいけない');
        }

        // BYE(片側だけ空き)は 16-9=7 件
        $byes = $first->filter(fn ($m) => ($m['p1'] === null) xor ($m['p2'] === null));
        $this->assertCount(7, $byes);
    }

    public function test_double_elimination_graph_routes_losers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('tournaments.store'), [
            'name' => 'ダブル大会',
            'format' => 'double',
            'participants_text' => "A\nB\nC\nD", // 4人 → きれいなダブルイリミ
        ])->assertRedirect();

        $bracket = \App\Models\Tournament::latest('id')->first()->bracket;
        $this->assertSame('double', $bracket['format']);

        $byId = collect($bracket['matches'])->keyBy('id');

        // WB決勝の勝者はGFのp1、敗者はLB決勝(L1_0)へ
        $this->assertSame(['GF', 'p1'], $byId['W1_0']['winnerTo']);
        $this->assertSame('L1_0', $byId['W1_0']['loserTo'][0]);
        // LB決勝の勝者はGFのp2
        $this->assertSame(['GF', 'p2'], $byId['L1_0']['winnerTo']);
        // GFは最終戦（行き先なし）
        $this->assertNull($byId['GF']['winnerTo']);
        // WB1回戦の敗者はLB1回戦へ
        $this->assertSame('L0_0', $byId['W0_0']['loserTo'][0]);
    }

    /**
     * ダブルイリミを最後まで進めて優勝者が確定するか（LB/GFの配線検証）。
     */
    public function test_double_elimination_resolves_a_champion(): void
    {
        foreach ([2, 4, 5, 8] as $n) {
            $bracket = $this->buildBracketViaController(
                array_map(fn ($i) => "P{$i}", range(1, $n)),
                'double',
            );
            $matches = $bracket['matches'];

            // 常に p1 を勝者に選び続け、収束させる
            for ($iter = 0; $iter < 60; $iter++) {
                $this->deriveBracket($matches);
                $changed = false;
                foreach ($matches as &$m) {
                    if ($m['p1'] !== null && $m['p2'] !== null && $m['pick'] === null) {
                        $m['pick'] = $m['p1'];
                        $changed = true;
                    }
                }
                unset($m);
                if (! $changed) {
                    break;
                }
            }
            $this->deriveBracket($matches);

            $final = collect($matches)->firstWhere('winnerTo', null);
            $this->assertNotNull($final['winner'], "n={$n}: 優勝者が確定していない");
        }
    }

    /** コントローラの buildBracket を呼ぶ */
    private function buildBracketViaController(array $players, string $format): array
    {
        $m = new \ReflectionMethod(\App\Http\Controllers\TournamentController::class, 'buildBracket');
        $m->setAccessible(true);

        return $m->invoke(new \App\Http\Controllers\TournamentController(), $players, $format);
    }

    /** 表示側 derive() の PHP 版（seed + pick から全スロットを導出） */
    private function deriveBracket(array &$matches): void
    {
        $by = [];
        foreach ($matches as $i => $m) {
            $by[$m['id']] = $i;
        }
        foreach ($matches as &$m) {
            if (! ($m['bracket'] === 'W' && $m['round'] === 0)) {
                $m['p1'] = null;
                $m['p2'] = null;
            }
            $m['winner'] = null;
        }
        unset($m);
        foreach ($matches as &$m) {
            $w = null;
            $l = null;
            if ($m['p1'] !== null && $m['p2'] !== null) {
                if ($m['pick'] === $m['p1'] || $m['pick'] === $m['p2']) {
                    $w = $m['pick'];
                    $l = $w === $m['p1'] ? $m['p2'] : $m['p1'];
                }
            } elseif ($m['p1'] !== null && $m['p2'] === null) {
                $w = $m['p1'];
            } elseif ($m['p1'] === null && $m['p2'] !== null) {
                $w = $m['p2'];
            }
            $m['winner'] = $w;
            if ($w !== null && $m['winnerTo']) {
                $matches[$by[$m['winnerTo'][0]]][$m['winnerTo'][1]] = $w;
            }
            if ($l !== null && $m['loserTo']) {
                $matches[$by[$m['loserTo'][0]]][$m['loserTo'][1]] = $l;
            }
        }
        unset($m);
    }

    public function test_weight_loss_challenge_ranks_by_loss_percent(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $challenge = \App\Models\Challenge::create([
            'user_id' => $a->id,
            'title' => '減量対決',
            'metric' => 'weight_loss',
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
        ]);
        $challenge->participants()->attach([$a->id, $b->id]);

        // A: 70 → 66.5kg(-5%) / B: 60 → 59.4kg(-1%)
        \App\Models\WeightRecord::insert([
            ['user_id' => $a->id, 'recorded_on' => '2026-06-02', 'weight_kg' => 70.0, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $a->id, 'recorded_on' => '2026-06-28', 'weight_kg' => 66.5, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $b->id, 'recorded_on' => '2026-06-02', 'weight_kg' => 60.0, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $b->id, 'recorded_on' => '2026-06-28', 'weight_kg' => 59.4, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $standings = $challenge->standings();
        $this->assertSame($a->id, $standings->first()['user']->id, '減量率が高いAが1位');
        $this->assertEqualsWithDelta(5.0, $standings->first()['value'], 0.01);
    }

    public function test_exercise_challenge_sums_minutes_in_period(): void
    {
        $u = User::factory()->create();
        $challenge = \App\Models\Challenge::create([
            'user_id' => $u->id, 'title' => '運動量', 'metric' => 'exercise_minutes',
            'starts_on' => '2026-06-01', 'ends_on' => '2026-06-30',
        ]);
        $challenge->participants()->attach($u->id);

        \App\Models\ExerciseRecord::insert([
            ['user_id' => $u->id, 'recorded_on' => '2026-06-05', 'activity' => '走', 'minutes' => 30, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $u->id, 'recorded_on' => '2026-06-10', 'activity' => '走', 'minutes' => 45, 'created_at' => now(), 'updated_at' => now()],
            // 期間外（集計されない）
            ['user_id' => $u->id, 'recorded_on' => '2026-07-01', 'activity' => '走', 'minutes' => 99, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertSame(75.0, $challenge->standings()->first()['value']);
    }

    public function test_sleep_allows_multiple_segments_per_day(): void
    {
        $user = User::factory()->create();

        // 同じ日に2区間（22:00-2:00 と 4:00-10:00）
        $this->actingAs($user)->post(route('sleep.store'), [
            'sleep_date' => '2026-06-21',
            'bed_at' => '2026-06-20T22:00',
            'wake_at' => '2026-06-21T02:00',
        ])->assertRedirect();
        $this->actingAs($user)->post(route('sleep.store'), [
            'sleep_date' => '2026-06-21',
            'bed_at' => '2026-06-21T04:00',
            'wake_at' => '2026-06-21T10:00',
        ])->assertRedirect();

        $segs = \App\Models\SleepRecord::where('user_id', $user->id)->get();
        $this->assertCount(2, $segs, '同じ日に2区間記録できる');
        $this->assertSame(4 * 60 + 6 * 60, (int) $segs->sum('duration_minutes'), '合計10時間');
    }

    public function test_match_record_winrate(): void
    {
        $user = User::factory()->create();
        foreach (['win', 'win', 'loss', 'draw'] as $i => $res) {
            $this->actingAs($user)->post(route('matches.store'), [
                'game' => 'スマブラ',
                'result' => $res,
                'played_on' => '2026-06-2' . $i,
            ])->assertRedirect();
        }

        // 2勝1敗1分 → 勝率 = 2/(2+1) = 67%
        $records = \App\Models\MatchRecord::where('user_id', $user->id)->get();
        $win = $records->where('result', 'win')->count();
        $loss = $records->where('result', 'loss')->count();
        $this->assertSame(2, $win);
        $this->assertSame(67, (int) round($win / ($win + $loss) * 100));
    }

    public function test_steam_resolve_handles_id_and_url(): void
    {
        config(['services.steam.key' => null]); // バニティ解決は無効（ネット非依存の分だけ検証）
        $svc = new \App\Services\SteamService();

        $this->assertSame('76561198000000000', $svc->resolveSteamId('76561198000000000'));
        $this->assertSame('76561198000000000', $svc->resolveSteamId('https://steamcommunity.com/profiles/76561198000000000/'));
        // バニティ名はキーが無いと解決できない → null
        $this->assertNull($svc->resolveSteamId('madgear'));
        $this->assertNull($svc->resolveSteamId(''));
    }

    public function test_registration_requires_invite_code_when_configured(): void
    {
        config(['portal.invite_code' => 'secret123']);

        // コード無し → 失敗（登録されない）
        $this->post('/register', [
            'name' => 'よそ者',
            'username' => 'stranger',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('invite_code');
        $this->assertDatabaseMissing('users', ['username' => 'stranger']);

        // 正しいコード → 登録成功
        $this->post('/register', [
            'name' => '身内',
            'username' => 'member',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_code' => 'secret123',
        ])->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', ['username' => 'member']);
    }

    public function test_admin_can_issue_password_reset_link_and_user_can_reset(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();

        // 一般ユーザーは発行できない
        $this->actingAs($member)
            ->post(route('admin.users.reset-link', $admin))
            ->assertForbidden();

        // 管理者が発行 → セッションにリンクが載る
        $res = $this->actingAs($admin)->post(route('admin.users.reset-link', $member));
        $res->assertSessionHas('reset_link');
        $url = session('reset_link')['url'];
        $this->assertStringContainsString('reset-password/', $url);

        // 本人がリンクを開いて新パスワードを設定
        auth()->logout();
        $token = \Illuminate\Support\Str::between($url, 'reset-password/', '?');
        $this->get($url)->assertOk()->assertSee('パスワード再設定');
        $this->post(route('password.update'), [
            'token' => $token,
            'username' => $member->username,
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertRedirect(route('login'));

        // 新パスワードでログインできる
        $this->post('/login', ['username' => $member->username, 'password' => 'new-password-456'])
            ->assertRedirect(route('dashboard'));

        // トークンは使い捨て（再利用は失敗）
        auth()->logout();
        $this->post(route('password.update'), [
            'token' => $token,
            'username' => $member->username,
            'password' => 'another-pass-789',
            'password_confirmation' => 'another-pass-789',
        ])->assertSessionHasErrors('username');
    }

    public function test_reset_with_invalid_token_fails(): void
    {
        $member = User::factory()->create(['password' => bcrypt('original-pass')]);

        $this->post(route('password.update'), [
            'token' => 'garbage-token',
            'username' => $member->username,
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertSessionHasErrors('username');

        // パスワードは変わっていない
        $this->post('/login', ['username' => $member->username, 'password' => 'original-pass'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_arcade_score_saves_and_awards_points_once_per_day(): void
    {
        $user = User::factory()->create();

        // 初回: 保存 + 5pt
        $res = $this->actingAs($user)->postJson(route('arcade.score'), ['game' => 'reaction', 'score' => 250]);
        $res->assertOk()->assertJson(['ok' => true, 'isBest' => true, 'earned' => 5]);
        $this->assertDatabaseHas('game_scores', ['user_id' => $user->id, 'game' => 'reaction', 'score' => 250]);

        // 同日2回目: 保存されるがポイントは付かない。ベスト更新も判定される
        $res = $this->actingAs($user)->postJson(route('arcade.score'), ['game' => 'reaction', 'score' => 200]);
        $res->assertOk()->assertJson(['ok' => true, 'isBest' => true, 'earned' => 0]);

        // 悪い記録はベスト扱いにならない
        $res = $this->actingAs($user)->postJson(route('arcade.score'), ['game' => 'reaction', 'score' => 400]);
        $res->assertOk()->assertJson(['isBest' => false]);

        // 不正なゲーム名・範囲外スコアは拒否
        $this->actingAs($user)->postJson(route('arcade.score'), ['game' => 'hack', 'score' => 100])
            ->assertStatus(422);
        $this->actingAs($user)->postJson(route('arcade.score'), ['game' => 'reaction', 'score' => 10])
            ->assertStatus(422);

        // ランキングページにベストが出る
        $this->actingAs($user)->get(route('arcade.index'))
            ->assertOk()->assertSee('200ms');
    }
}
