<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Document extends Model
{
    protected $fillable = ['user_id', 'category', 'title', 'body', 'is_public'];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
