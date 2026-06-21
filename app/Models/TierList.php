<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TierList extends Model
{
    protected $fillable = [
        'user_id', 'title', 'description', 'tiers', 'is_public',
        'is_template', 'template_id', 'pool',
    ];

    protected $casts = [
        'tiers' => 'array',
        'pool' => 'array',
        'is_public' => 'boolean',
        'is_template' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 元になったテンプレート */
    public function template(): BelongsTo
    {
        return $this->belongsTo(TierList::class, 'template_id');
    }

    /** このテンプレートから作られたランキング */
    public function rankings(): HasMany
    {
        return $this->hasMany(TierList::class, 'template_id');
    }

    /**
     * このリストに含まれる全項目（各Tier + 未分類）をまとめて返す。
     * テンプレートから新しいランキングを作るときの項目プールに使う。
     */
    public function allItems(): array
    {
        $items = [];
        foreach (($this->tiers ?? []) as $tier) {
            foreach (($tier['items'] ?? []) as $item) {
                $items[] = $item;
            }
        }
        foreach (($this->pool ?? []) as $item) {
            $items[] = $item;
        }

        return array_values(array_unique($items));
    }
}
