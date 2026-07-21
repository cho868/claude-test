<?php

namespace App\Http\Controllers;

use App\Models\Whiteboard;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WhiteboardController extends Controller
{
    /** dataURLの最大サイズ(約4MB)。手書きPNGは通常これより十分小さい。 */
    private const MAX_DATA_LEN = 4_000_000;

    public function index()
    {
        $boards = Whiteboard::with('user')
            ->where(fn ($q) => $q->where('is_public', true)->orWhere('user_id', auth()->id()))
            ->latest('updated_at')
            ->get();

        return view('whiteboards.index', compact('boards'));
    }

    public function create()
    {
        return view('whiteboards.edit', ['board' => new Whiteboard(['title' => '無題', 'is_public' => true])]);
    }

    public function store(Request $request, PointService $points)
    {
        $data = $this->validated($request);

        $board = $request->user()->whiteboards()->create($data);
        $points->award($request->user(), 5, 'whiteboard', 'ホワイトボードを作成');

        return redirect()->route('whiteboards.show', $board)->with('status', 'ホワイトボードを保存しました。');
    }

    public function show(Whiteboard $whiteboard)
    {
        $this->authorizeView($whiteboard);

        return view('whiteboards.show', ['board' => $whiteboard]);
    }

    public function edit(Whiteboard $whiteboard)
    {
        $this->authorizeOwner($whiteboard);

        return view('whiteboards.edit', ['board' => $whiteboard]);
    }

    public function update(Request $request, Whiteboard $whiteboard)
    {
        $this->authorizeOwner($whiteboard);
        $whiteboard->update($this->validated($request));

        return redirect()->route('whiteboards.show', $whiteboard)->with('status', 'ホワイトボードを更新しました。');
    }

    public function destroy(Whiteboard $whiteboard)
    {
        $this->authorizeOwner($whiteboard);
        $whiteboard->delete();

        return redirect()->route('whiteboards.index')->with('status', 'ホワイトボードを削除しました。');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'image_data' => ['required', 'string', 'max:' . self::MAX_DATA_LEN],
            'is_public' => ['nullable', 'boolean'],
        ]);

        // PNGのdataURLだけ受け付ける（他形式・スクリプト混入を防ぐ）
        if (! str_starts_with($data['image_data'], 'data:image/png;base64,')) {
            throw ValidationException::withMessages(['image_data' => '画像の形式が不正です。']);
        }

        $data['is_public'] = $request->boolean('is_public');

        return $data;
    }

    private function authorizeView(Whiteboard $board): void
    {
        abort_unless($board->is_public || $board->user_id === auth()->id() || auth()->user()->is_admin, 403);
    }

    private function authorizeOwner(Whiteboard $board): void
    {
        abort_unless($board->user_id === auth()->id() || auth()->user()->is_admin, 403);
    }
}
