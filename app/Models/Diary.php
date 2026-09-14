<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Diary extends Model
{
    /** 気分（キー => [絵文字, ラベル]） */
    public const MOODS = [
        'great' => ['😆', '最高'],
        'good' => ['🙂', 'いい感じ'],
        'normal' => ['😐', 'ふつう'],
        'tired' => ['😪', '疲れた'],
        'bad' => ['😢', 'しんどい'],
        'angry' => ['😡', 'イライラ'],
    ];

    /** 天気（キー => [絵文字, ラベル]） */
    public const WEATHERS = [
        'sunny' => ['☀️', '晴れ'],
        'cloudy' => ['☁️', 'くもり'],
        'rainy' => ['☔', '雨'],
        'snowy' => ['⛄', '雪'],
        'hot' => ['🥵', '猛暑'],
        'cold' => ['🥶', '寒波'],
    ];

    protected $fillable = [
        'user_id', 'entry_date', 'title', 'body', 'mood', 'weather', 'visibility',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 指定ユーザーが閲覧できる日記に絞り込む。
     * 日記は私的なものなので、管理者であっても他人の private は見えない。
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        $userId = $user?->id;

        return $query->where(function ($q) use ($userId) {
            $q->where('visibility', 'members')
                ->orWhere('user_id', $userId);
        });
    }

    public function canBeViewedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->visibility === 'members' || $this->user_id === $user->id;
    }

    /** 編集・削除は本人だけ（管理者にも開けない） */
    public function canBeEditedBy(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }

    public function isShared(): bool
    {
        return $this->visibility === 'members';
    }

    public function displayTitle(): string
    {
        return $this->title ?: $this->entry_date->format('n月j日の日記');
    }

    public function moodIcon(): ?string
    {
        return self::MOODS[$this->mood][0] ?? null;
    }

    public function moodLabel(): ?string
    {
        return self::MOODS[$this->mood][1] ?? null;
    }

    public function weatherIcon(): ?string
    {
        return self::WEATHERS[$this->weather][0] ?? null;
    }

    public function weatherLabel(): ?string
    {
        return self::WEATHERS[$this->weather][1] ?? null;
    }

    /**
     * Markdown 本文を安全な HTML に変換する。
     * 生 HTML は除去し、危険なリンクも無効化して XSS を防ぐ。
     */
    public function renderedBody(): string
    {
        return Str::markdown($this->body ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /** 一覧用の抜粋（Markdown 記法を除いた先頭テキスト） */
    public function excerpt(int $length = 100): string
    {
        $plain = trim(preg_replace('/\s+/', ' ',
            preg_replace('/[#>*`_\-\[\]!]+/', '', strip_tags($this->body ?? ''))
        ));

        return Str::limit($plain, $length);
    }
}
