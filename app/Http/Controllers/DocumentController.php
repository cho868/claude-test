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

        $query = Document::with('user')
            ->where(fn ($q) => $q->where('is_public', true)->orWhere('user_id', auth()->id()));

        if ($category) {
            $query->where('category', $category);
        }

        $documents = $query->latest()->paginate(15)->withQueryString();

        // カテゴリ一覧（絞り込み用）
        $categories = Document::query()
            ->where('is_public', true)
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
            'is_public' => $request->boolean('is_public'),
        ]);

        $points->award($request->user(), 15, 'create_document', "資料「{$document->title}」作成");

        return redirect()->route('documents.show', $document)
            ->with('status', '資料を公開しました(+15pt)');
    }

    public function show(Document $document)
    {
        abort_unless($document->is_public || $document->user_id === auth()->id(), 403);

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

        $document->update($this->validateData($request) + [
            'is_public' => $request->boolean('is_public'),
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
        return $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);
    }

    private function authorizeOwner(Document $document): void
    {
        abort_unless(
            $document->user_id === auth()->id() || auth()->user()->is_admin,
            403,
        );
    }
}
