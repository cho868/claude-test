<?php

namespace App\Http\Controllers;

use App\Models\TierList;
use App\Services\PointService;
use Illuminate\Http\Request;

class TierListController extends Controller
{
    public function index()
    {
        $visible = fn ($q) => $q->where('is_public', true)->orWhere('user_id', auth()->id());

        $templates = TierList::with('user')->withCount('rankings')
            ->where('is_template', true)->where($visible)
            ->latest()->get();

        $rankings = TierList::with('user', 'template')
            ->where('is_template', false)->where($visible)
            ->latest()->paginate(12);

        return view('tierlists.index', compact('templates', 'rankings'));
    }

    public function create(Request $request)
    {
        $mode = $request->query('mode') === 'template' ? 'template' : 'ranking';
        $initialPool = [];
        $templateId = null;
        $templateTitle = null;

        // 既存リスト/テンプレから「自分のランキングを作る」: 項目をプールに読み込む
        if ($from = $request->query('from')) {
            $src = TierList::findOrFail($from);
            abort_unless($src->is_public || $src->user_id === auth()->id(), 403);
            $initialPool = $src->allItems();
            $templateId = $src->id;
            $templateTitle = $src->title;
            $mode = 'ranking';
        }

        return view('tierlists.create', compact('mode', 'initialPool', 'templateId', 'templateTitle'));
    }

    public function store(Request $request, PointService $points)
    {
        $data = $this->validateData($request);
        $isTemplate = $request->boolean('is_template');

        $tierList = TierList::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'tiers' => json_decode($data['tiers'], true),
            'pool' => json_decode($data['pool'] ?? '[]', true),
            'is_template' => $isTemplate,
            'template_id' => $request->input('template_id') ?: null,
            'is_public' => $request->boolean('is_public'),
        ]);

        $label = $isTemplate ? 'テンプレート' : 'Tierリスト';
        $points->award($request->user(), 10, 'create_tierlist', "{$label}「{$tierList->title}」作成");

        return redirect()->route('tierlists.show', $tierList)
            ->with('status', "{$label}を作成しました(+10pt)");
    }

    public function show(TierList $tierlist)
    {
        abort_unless($tierlist->is_public || $tierlist->user_id === auth()->id(), 403);
        $tierlist->load('user', 'template');

        return view('tierlists.show', ['tierList' => $tierlist]);
    }

    public function edit(TierList $tierlist)
    {
        $this->authorizeOwner($tierlist);

        return view('tierlists.create', [
            'tierList' => $tierlist,
            'mode' => $tierlist->is_template ? 'template' : 'ranking',
            'initialPool' => $tierlist->pool ?? [],
            'templateId' => $tierlist->template_id,
            'templateTitle' => $tierlist->template?->title,
        ]);
    }

    public function update(Request $request, TierList $tierlist)
    {
        $this->authorizeOwner($tierlist);
        $data = $this->validateData($request);

        $tierlist->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'tiers' => json_decode($data['tiers'], true),
            'pool' => json_decode($data['pool'] ?? '[]', true),
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
            'pool' => ['nullable', 'json'],
            'template_id' => ['nullable', 'exists:tier_lists,id'],
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
