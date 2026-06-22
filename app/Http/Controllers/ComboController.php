<?php

namespace App\Http\Controllers;

use App\Models\ComboEntry;
use Illuminate\Http\Request;

class ComboController extends Controller
{
    public function index(Request $request)
    {
        $character = $request->query('character');

        $query = ComboEntry::with('user')
            ->where(fn ($q) => $q->where('is_public', true)->orWhere('user_id', auth()->id()));

        if ($character) {
            $query->where('character', $character);
        }

        $entries = $query->orderBy('character')->orderBy('starter')->get();

        // キャラ → 始動 → ヒット状況 の入れ子にまとめる
        $grouped = $entries->groupBy('character')->map(
            fn ($byChar) => $byChar->groupBy('starter')
        );

        $characters = ComboEntry::query()
            ->where('is_public', true)
            ->select('character')->distinct()->orderBy('character')->pluck('character');

        return view('combos.index', compact('grouped', 'characters', 'character'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'character' => ['required', 'string', 'max:50'],
            'starter' => ['required', 'string', 'max:80'],
            'hit_type' => ['required', 'in:normal,counter,punish'],
            'combo' => ['required', 'string', 'max:1000'],
            'damage' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->comboEntries()->create($data + ['is_public' => $request->boolean('is_public', true)]);

        return back()->with('status', 'コンボを追加しました。');
    }

    public function destroy(ComboEntry $combo)
    {
        abort_unless($combo->user_id === auth()->id() || auth()->user()->is_admin, 403);
        $combo->delete();

        return back()->with('status', '削除しました。');
    }
}
