<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Document extends Model
{
    protected $fillable = ['user_id', 'category', 'title', 'body', 'is_public', 'visibility'];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 指定ユーザーが閲覧できる資料に絞り込むスコープ。
     * - admin: すべて
     * - それ以外: visibility=members、または admin可視を除く自分の private/members
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        if ($user && $user->is_admin) {
            return $query; // 管理者は全件
        }

        $userId = $user?->id;

        return $query->where(function ($q) use ($userId) {
            $q->where('visibility', 'members')
                ->orWhere(function ($q2) use ($userId) {
                    $q2->where('user_id', $userId)
                        ->whereIn('visibility', ['private', 'members']);
                });
        });
    }

    public function canBeViewedBy(?User $user): bool
    {
        if ($user && $user->is_admin) {
            return true;
        }
        if ($this->visibility === 'members') {
            return true;
        }

        // private は著者のみ、admin は管理者のみ（上で処理済み）
        return $user && $this->user_id === $user->id && $this->visibility !== 'admin';
    }

    public function visibilityLabel(): string
    {
        return match ($this->visibility) {
            'admin' => '管理者のみ',
            'private' => '自分のみ',
            default => '身内に公開',
        };
    }

    /**
     * Markdown 本文を安全な HTML に変換する。
     * 生 HTML は除去・危険なリンクも無効化して XSS を防ぐ。
     */
    public function renderedBody(): string
    {
        return Str::markdown($this->body ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * 一覧用の抜粋（Markdown 記法を除いた先頭テキスト）。
     */
    public function excerpt(int $length = 120): string
    {
        $plain = trim(preg_replace('/[#>*`_\-\[\]!]+/', '', strip_tags($this->body ?? '')));

        return Str::limit($plain, $length);
    }
}
