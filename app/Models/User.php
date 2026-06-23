<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'discord_id',
        'steam_id',
        'avatar_style',
        'avatar_emoji',
        'avatar_color',
        'avatar_variant',
        'avatar_seed',
        'target_weight_kg',
        'weekly_exercise_goal',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_date' => 'date',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'target_weight_kg' => 'decimal:2',
            'weekly_exercise_goal' => 'integer',
        ];
    }

    /** DiceBear（自動生成イラスト）のURL。アップロード不要・著作権フリー。 */
    public function avatarDicebearUrl(): string
    {
        $variant = $this->avatar_variant ?: 'fun-emoji';
        $seed = rawurlencode($this->avatar_seed ?: $this->name);

        return "https://api.dicebear.com/9.x/{$variant}/svg?seed={$seed}";
    }

    /** 名前の頭文字（絵文字未設定時のフォールバック） */
    public function initial(): string
    {
        return mb_strtoupper(mb_substr(trim($this->name), 0, 1)) ?: '?';
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    public function pointLogs(): HasMany
    {
        return $this->hasMany(PointLog::class)->latest();
    }

    public function sleepRecords(): HasMany
    {
        return $this->hasMany(SleepRecord::class);
    }

    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    public function memos(): HasMany
    {
        return $this->hasMany(Memo::class);
    }

    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }

    public function tierLists(): HasMany
    {
        return $this->hasMany(TierList::class);
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class);
    }

    public function scheduleEvents(): HasMany
    {
        return $this->hasMany(ScheduleEvent::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function weightRecords(): HasMany
    {
        return $this->hasMany(WeightRecord::class);
    }

    public function exerciseRecords(): HasMany
    {
        return $this->hasMany(ExerciseRecord::class);
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class);
    }

    public function matchRecords(): HasMany
    {
        return $this->hasMany(MatchRecord::class);
    }

    public function gameRoutines(): HasMany
    {
        return $this->hasMany(GameRoutine::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    /**
     * 現在のポイントで到達できる最高位の称号。
     */
    public function currentTitle(): ?Title
    {
        return $this->title
            ?? Title::where('required_points', '<=', $this->points)
                ->orderByDesc('required_points')
                ->first();
    }

    /**
     * 次の称号(まだ到達していない最も近いもの)。
     */
    public function nextTitle(): ?Title
    {
        return Title::where('required_points', '>', $this->points)
            ->orderBy('required_points')
            ->first();
    }
}
