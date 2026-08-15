<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public const CATEGORIES = [
        'expense' => [
            'food' => ['🍚', '食費'],
            'daily' => ['🧻', '日用品'],
            'transport' => ['🚃', '交通'],
            'hobby' => ['🎮', '趣味・娯楽'],
            'utility' => ['💡', '光熱費'],
            'comm' => ['📱', '通信'],
            'housing' => ['🏠', '住居'],
            'medical' => ['💊', '医療'],
            'social' => ['🍻', '交際費'],
            'clothes' => ['👕', '衣類'],
            'other' => ['📦', 'その他'],
        ],
        'income' => [
            'salary' => ['💰', '給与'],
            'side' => ['💻', '副収入'],
            'gift' => ['🎁', '臨時収入'],
            'other_in' => ['📥', 'その他'],
        ],
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $month = $this->month($request);
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $items = Expense::where('user_id', $user->id)
            ->whereBetween('spent_on', [$start, $end])
            ->orderByDesc('spent_on')->orderByDesc('id')
            ->get();

        $expense = (int) $items->where('kind', 'expense')->sum('amount');
        $income = (int) $items->where('kind', 'income')->sum('amount');

        // カテゴリ別（支出のみ・多い順）
        $byCategory = $items->where('kind', 'expense')
            ->groupBy('category')
            ->map(fn ($g) => (int) $g->sum('amount'))
            ->sortDesc();

        // 直近6か月の推移
        $trend = collect(range(5, 0))->map(function ($back) use ($user, $month) {
            $m = $month->copy()->subMonths($back);
            $rows = Expense::where('user_id', $user->id)
                ->whereBetween('spent_on', [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()])
                ->selectRaw("kind, SUM(amount) as total")->groupBy('kind')->pluck('total', 'kind');

            return [
                'label' => $m->format('n月'),
                'expense' => (int) ($rows['expense'] ?? 0),
                'income' => (int) ($rows['income'] ?? 0),
            ];
        });

        // 身内で共有された支出（共同購入・割り勘の把握用）
        $shared = Expense::with('user')
            ->where('is_shared', true)
            ->where('kind', 'expense')
            ->whereBetween('spent_on', [$start, $end])
            ->orderByDesc('spent_on')->get();

        return view('expenses.index', [
            'items' => $items,
            'month' => $month,
            'expense' => $expense,
            'income' => $income,
            'balance' => $income - $expense,
            'byCategory' => $byCategory,
            'trend' => $trend,
            'categories' => self::CATEGORIES,
            'budget' => $user->monthly_budget,
            'shared' => $shared,
            'sharedTotal' => (int) $shared->sum('amount'),
        ]);
    }

    public function store(Request $request, PointService $points)
    {
        $kind = $request->input('kind', 'expense');
        $data = $request->validate([
            'kind' => ['required', Rule::in(['expense', 'income'])],
            'spent_on' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1', 'max:99999999'],
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES[$kind] ?? self::CATEGORIES['expense']))],
            'memo' => ['nullable', 'string', 'max:100'],
            'is_shared' => ['nullable', 'boolean'],
        ]);

        $request->user()->expenses()->create($data + ['is_shared' => $request->boolean('is_shared')]);

        // 記録の習慣づけに1日1回だけポイント
        if (! $request->user()->pointLogs()->where('reason', 'expense')->whereDate('created_at', today())->exists()) {
            $points->award($request->user(), 3, 'expense', '家計簿をつけた');
        }

        return back()->with('status', '記録しました。');
    }

    public function destroy(Expense $expense)
    {
        abort_unless($expense->user_id === auth()->id(), 403);
        $expense->delete();

        return back()->with('status', '削除しました。');
    }

    public function budget(Request $request)
    {
        $data = $request->validate(['monthly_budget' => ['nullable', 'integer', 'min:0', 'max:99999999']]);
        $request->user()->forceFill($data)->save();

        return back()->with('status', '予算を更新しました。');
    }

    private function month(Request $request): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m', (string) $request->query('m'))->startOfMonth();
        } catch (\Throwable $e) {
            return Carbon::now()->startOfMonth();
        }
    }
}
