<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Task extends Model
{
    protected $fillable = [
        'user_id', 'title', 'note', 'due_date', 'list', 'visibility', 'done_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'done_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 期限の早い順（期限なしは最後）→ 登録順 */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('id');
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }

    public function isShared(): bool
    {
        return $this->visibility === 'members';
    }

    /**
     * 見られるのは本人と、共有された分だけ。
     * 日記と同じく個人的な内容が入るので、管理者バイパスは入れない。
     */
    public function canBeViewedBy(?User $user): bool
    {
        return $user !== null && ($this->isShared() || $this->user_id === $user->id);
    }

    public function canBeEditedBy(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }

    /**
     * 画面の見出し用の区分。
     * overdue / today / week / later / none（期限なし） / done
     */
    public function bucket(?Carbon $today = null): string
    {
        $today ??= Carbon::today();

        if ($this->isDone()) {
            return 'done';
        }
        if (! $this->due_date) {
            return 'none';
        }
        if ($this->due_date->lt($today)) {
            return 'overdue';
        }
        if ($this->due_date->equalTo($today)) {
            return 'today';
        }

        return $this->due_date->lte($today->copy()->addDays(7)) ? 'week' : 'later';
    }

    /** 「あと3日」「2日遅れ」のような期限の表示 */
    public function dueLabel(?Carbon $today = null): ?string
    {
        if (! $this->due_date) {
            return null;
        }

        $today ??= Carbon::today();
        $days = (int) $today->diffInDays($this->due_date, false);
        $date = $this->due_date->isoFormat('M/D(ddd)');

        return match (true) {
            $this->isDone() => $date,
            $days < 0 => $date.'・'.abs($days).'日遅れ',
            $days === 0 => $date.'・今日',
            $days === 1 => $date.'・明日',
            default => $date.'・あと'.$days.'日',
        };
    }

    /** メモは Markdown。生HTMLは除去する（XSS対策） */
    public function renderedNote(): string
    {
        return Str::markdown($this->note ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * まとめて貼り付けたテキストをタスクに分解する（保存はしない）。
     *
     *   # 年末調整          ← 見出しで以降のリスト名を切り替える
     *   10/6 Oliveに入金    ← 先頭の日付が期限になる（年なしは近い未来として解釈）
     *   - 2027/1/10 申請書を返送
     *   保険の証明書を探す  ← 日付なしでもOK
     *
     * @return list<array{title:string,due_date:?string,list:?string}>
     */
    public static function parseBulk(string $text, ?string $list = null, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $items = [];

        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // 見出し → リスト名
            if (preg_match('/^#{1,6}\s*(.+)$/u', $line, $m)) {
                $list = Str::limit(trim($m[1]), 40, '');

                continue;
            }

            // 箇条書き・チェックボックスの記号を外す
            $line = trim(preg_replace('/^(?:[-*・]|\d+[.)])\s*(?:\[[ xX]\]\s*)?/u', '', $line));

            $due = null;
            // 2027/1/10, 2027-01-10, 10/6, 10/6(火) など。表の区切り「|」も許す
            if (preg_match('#^\|?\s*(?:(\d{4})[/\-年.])?(\d{1,2})[/\-月.](\d{1,2})日?(?:\s*[（(][^）)]*[）)])?(?:\s*まで)?\s*[|：:、,]?\s*(.*)$#u', $line, $m)) {
                [$all, $y, $mo, $d, $rest] = $m;
                if (checkdate((int) $mo, (int) $d, (int) ($y ?: $today->year))) {
                    $date = Carbon::create((int) ($y ?: $today->year), (int) $mo, (int) $d)->startOfDay();
                    // 年の書いていない日付が2か月以上前なら来年のこと（例: 9月に書いた「1/10」）
                    if (! $y && $date->lt($today->copy()->subMonths(2))) {
                        $date->addYear();
                    }
                    $due = $date->toDateString();
                    $line = $rest;
                }
            }

            // 表の残りの「|」や強調の ** を取り除く
            $title = trim(preg_replace('/\s*\|\s*/u', ' ', str_replace('**', '', $line)));

            if ($title === '') {
                continue;
            }

            $items[] = [
                'title' => Str::limit($title, 120, ''),
                'due_date' => $due,
                'list' => $list,
            ];
        }

        return $items;
    }
}
