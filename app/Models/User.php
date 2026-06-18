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
        ];
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
