<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyVote;
use App\Services\PointService;
use Illuminate\Http\Request;

class SurveyController extends Controller
{
    public function index()
    {
        $surveys = Survey::with('user')
            ->withCount('votes')
            ->latest()
            ->paginate(12);

        return view('surveys.index', compact('surveys'));
    }

    public function create()
    {
        return view('surveys.create');
    }

    public function store(Request $request, PointService $points)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'multiple_choice' => ['nullable', 'boolean'],
            'closes_at' => ['nullable', 'date'],
            'options' => ['required', 'array', 'min:2'],
            'options.*' => ['nullable', 'string', 'max:255'],
        ]);

        $options = collect($validated['options'])->map(fn ($o) => trim($o))->filter()->values();
        abort_if($options->count() < 2, 422, '選択肢は2つ以上必要です。');

        $survey = Survey::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'multiple_choice' => $request->boolean('multiple_choice'),
            'closes_at' => $validated['closes_at'] ?? null,
        ]);

        $options->each(fn ($label, $i) => $survey->options()->create([
            'label' => $label,
            'sort_order' => $i,
        ]));

        $points->award($request->user(), 10, 'create_survey', "アンケート「{$survey->title}」作成");

        return redirect()->route('surveys.show', $survey)
            ->with('status', 'アンケートを作成しました(+10pt)');
    }

    public function show(Survey $survey)
    {
        $survey->load(['user', 'options.votes']);

        $myVotes = SurveyVote::where('survey_id', $survey->id)
            ->where('user_id', auth()->id())
            ->pluck('survey_option_id')
            ->all();

        return view('surveys.show', compact('survey', 'myVotes'));
    }

    public function vote(Request $request, Survey $survey)
    {
        abort_if($survey->is_closed, 403, 'このアンケートは締め切られています。');

        $validated = $request->validate([
            'options' => ['required', 'array', 'min:1'],
            'options.*' => ['integer', 'exists:survey_options,id'],
        ]);

        $optionIds = $survey->options()->pluck('id')->all();
        $chosen = array_values(array_intersect($validated['options'], $optionIds));
        abort_if(empty($chosen), 422, '無効な選択肢です。');

        if (! $survey->multiple_choice) {
            $chosen = [reset($chosen)];
        }

        // 既存投票を削除して入れ直す(投票変更を許可)
        SurveyVote::where('survey_id', $survey->id)
            ->where('user_id', auth()->id())
            ->delete();

        foreach ($chosen as $optionId) {
            SurveyVote::create([
                'survey_id' => $survey->id,
                'survey_option_id' => $optionId,
                'user_id' => auth()->id(),
            ]);
        }

        return back()->with('status', '投票しました。');
    }

    public function close(Survey $survey)
    {
        abort_unless($survey->user_id === auth()->id() || auth()->user()->is_admin, 403);
        $survey->update(['is_closed' => ! $survey->is_closed]);

        return back()->with('status', $survey->is_closed ? '締め切りました。' : '再開しました。');
    }

    public function destroy(Survey $survey)
    {
        abort_unless($survey->user_id === auth()->id() || auth()->user()->is_admin, 403);
        $survey->delete();

        return redirect()->route('surveys.index')->with('status', '削除しました。');
    }
}
