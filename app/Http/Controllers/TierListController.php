<?php

namespace App\Http\Controllers;

use App\Models\TierList;
use App\Services\PointService;
use Illuminate\Http\Request;

class TierListController extends Controller
{
    public function index()
    {
        $tierLists = TierList::with('user')
            ->where('is_public', true)
            ->orWhere('user_id', auth()->id())
            ->latest()
            ->paginate(12);

        return view('tierlists.index', compact('tierLists'));
    }

    public function create()
    {
        return view('tierlists.create');
    }

    public function store(Request $request, PointService $points)
    {
        $validated = $this->validateData($request);

        $tierList = TierList::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'tiers' => json_decode($validated['tiers'], true),
            'is_public' => $request->boolean('is_public'),
        ]);

        $points->award($request->user(), 10, 'create_tierlist', "ソート「{$tierList->title}」作成");

        return redirect()->route('tierlists.show', $tierList)
            ->with('status', 'ソート/ランキングを作成しました(+10pt)');
    }

    public function show(TierList $tierlist)
    {
        abort_unless($tierlist->is_public || $tierlist->user_id === auth()->id(), 403);
        $tierlist->load('user');

        return view('tierlists.show', ['tierList' => $tierlist]);
    }

    public function edit(TierList $tierlist)
    {
        $this->authorizeOwner($tierlist);

        return view('tierlists.create', ['tierList' => $tierlist]);
    }

    public function update(Request $request, TierList $tierlist)
    {
        $this->authorizeOwner($tierlist);
        $validated = $this->validateData($request);

        $tierlist->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'tiers' => json_decode($validated['tiers'], true),
            'is_public' => $request->boolean('is_public'),
        ]);

        return redirect()->route('tierlists.show', $tierlist)->with('status', '更新しました。');
    }

    public function destroy(TierList $tierlist)
    {
        $this->authorizeOwner($tierlist);
        $tierlist->delete();

        return redirect()->route('tierlists.index')->with('status', '削除しました。');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'tiers' => ['required', 'json'],
        ]);
    }

    private function authorizeOwner(TierList $tierList): void
    {
        abort_unless(
            $tierList->user_id === auth()->id() || auth()->user()->is_admin,
            403,
        );
    }
}
