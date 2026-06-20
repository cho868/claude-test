<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\PointService;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->query('category');

        $query = Document::with('user')->visibleTo($request->user());

        if ($category) {
            $query->where('category', $category);
        }

        $documents = $query->latest()->paginate(15)->withQueryString();

        // カテゴリ一覧(閲覧可能な資料から)
        $categories = Document::query()
            ->visibleTo($request->user())
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('documents.index', compact('documents', 'categories', 'category'));
    }

    public function create()
    {
        return view('documents.create');
    }

    public function store(Request $request, PointService $points)
    {
        $data = $this->validateData($request);

        $document = $request->user()->documents()->create($data + [
            'is_public' => $data['visibility'] !== 'private',
        ]);

        $points->award($request->user(), 15, 'create_document', "資料「{$document->title}」作成");

        return redirect()->route('documents.show', $document)
            ->with('status', '資料を保存しました(+15pt)');
    }

    public function show(Request $request, Document $document)
    {
        abort_unless($document->canBeViewedBy($request->user()), 403);

        $document->increment('views');
        $document->load('user');

        return view('documents.show', compact('document'));
    }

    public function edit(Document $document)
    {
        $this->authorizeOwner($document);

        return view('documents.create', ['document' => $document]);
    }

    public function update(Request $request, Document $document)
    {
        $this->authorizeOwner($document);
        $data = $this->validateData($request);

        $document->update($data + [
            'is_public' => $data['visibility'] !== 'private',
        ]);

        return redirect()->route('documents.show', $document)->with('status', '資料を更新しました。');
    }

    public function destroy(Document $document)
    {
        $this->authorizeOwner($document);
        $document->delete();

        return redirect()->route('documents.index')->with('status', '資料を削除しました。');
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'visibility' => ['required', 'in:members,admin,private'],
        ]);

        // 管理者のみ「管理者限定」を設定できる
        if ($data['visibility'] === 'admin' && ! $request->user()->is_admin) {
            $data['visibility'] = 'members';
        }

        return $data;
    }

    private function authorizeOwner(Document $document): void
    {
        abort_unless(
            $document->user_id === auth()->id() || auth()->user()->is_admin,
            403,
        );
    }
}
