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
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));

        $user = User::firstWhere('email', 'taro@example.com');
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

    public function test_registration_requires_invite_code_when_configured(): void
    {
        config(['portal.invite_code' => 'secret123']);

        // コード無し → 失敗（登録されない）
        $this->post('/register', [
            'name' => 'よそ者',
            'email' => 'stranger@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('invite_code');
        $this->assertDatabaseMissing('users', ['email' => 'stranger@example.com']);

        // 正しいコード → 登録成功
        $this->post('/register', [
            'name' => '身内',
            'email' => 'member@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_code' => 'secret123',
        ])->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', ['email' => 'member@example.com']);
    }
}
