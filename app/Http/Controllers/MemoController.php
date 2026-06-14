<?php

namespace App\Http\Controllers;

use App\Models\Memo;
use Illuminate\Http\Request;

class MemoController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->query('category', 'gmq2');

        $memos = Memo::where('user_id', auth()->id())
            ->where('category', $category)
            ->latest()
            ->get();

        // 公開メモ(他の身内が共有したもの)
        $shared = Memo::with('user')
            ->where('category', $category)
            ->where('is_public', true)
            ->where('user_id', '!=', auth()->id())
            ->latest()
            ->take(20)
            ->get();

        return view('memos.index', compact('memos', 'shared', 'category'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $request->user()->memos()->create([
            'category' => $validated['category'],
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            'is_public' => $request->boolean('is_public'),
        ]);

        return back()->with('status', 'メモを保存しました。');
    }

    public function update(Request $request, Memo $memo)
    {
        $this->authorizeOwner($memo);

        $memo->update($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
        ]) + ['is_public' => $request->boolean('is_public')]);

        return back()->with('status', 'メモを更新しました。');
    }

    public function destroy(Memo $memo)
    {
        $this->authorizeOwner($memo);
        $category = $memo->category;
        $memo->delete();

        return redirect()->route('memos.index', ['category' => $category])
            ->with('status', 'メモを削除しました。');
    }

    private function authorizeOwner(Memo $memo): void
    {
        abort_unless($memo->user_id === auth()->id(), 403);
    }
}
