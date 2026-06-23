<?php

namespace App\Http\Controllers;

use App\Models\Link;
use Illuminate\Http\Request;

class LinkController extends Controller
{
    public function index()
    {
        $links = Link::with('user')
            ->where(fn ($q) => $q->where('is_public', true)->orWhere('user_id', auth()->id()))
            ->orderBy('category')->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('category');

        $categories = Link::query()->select('category')->distinct()->orderBy('category')->pluck('category');

        return view('links.index', compact('links', 'categories'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $request->user()->links()->create($data + ['is_public' => $request->boolean('is_public', true)]);

        return back()->with('status', 'リンクを追加しました。');
    }

    public function update(Request $request, Link $link)
    {
        $this->authorizeOwner($link);
        $link->update($this->validateData($request) + ['is_public' => $request->boolean('is_public')]);

        return back()->with('status', 'リンクを更新しました。');
    }

    public function destroy(Link $link)
    {
        $this->authorizeOwner($link);
        $link->delete();

        return back()->with('status', 'リンクを削除しました。');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url', 'max:500'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:8'],
        ]);
    }

    private function authorizeOwner(Link $link): void
    {
        abort_unless($link->user_id === auth()->id() || auth()->user()->is_admin, 403);
    }
}
