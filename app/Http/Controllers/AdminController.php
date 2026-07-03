<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\GameSession;
use App\Models\ScheduleEvent;
use App\Models\SetupTask;
use App\Models\Survey;
use App\Models\User;
use App\Services\ServerStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class AdminController extends Controller
{
    public function index(ServerStats $serverStats)
    {
        $tasks = SetupTask::orderBy('sort_order')->get()->groupBy('category');
        $taskDone = SetupTask::where('done', true)->count();
        $taskTotal = SetupTask::count();

        $server = [
            'disk' => $serverStats->disk(),
            'memory' => $serverStats->memory(),
        ];

        $stats = [
            'users' => User::count(),
            'admins' => User::where('is_admin', true)->count(),
            'documents' => Document::count(),
            'surveys' => Survey::count(),
            'events' => ScheduleEvent::count(),
            'game_minutes' => (int) GameSession::sum('minutes'),
        ];

        return view('admin.index', compact('tasks', 'taskDone', 'taskTotal', 'stats', 'server'));
    }

    public function toggleTask(SetupTask $task)
    {
        $task->update(['done' => ! $task->done]);

        return back()->with('status', $task->title . ' を ' . ($task->done ? '完了' : '未完了') . ' にしました。');
    }

    public function server(ServerStats $stats)
    {
        return view('admin.server', ['s' => $stats->all()]);
    }

    public function users()
    {
        $users = User::with('title')->orderByDesc('points')->paginate(30);

        return view('admin.users', compact('users'));
    }

    public function toggleAdmin(Request $request, User $user)
    {
        // 自分自身の権限は誤って外せないようにする
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['admin' => '自分自身の管理者権限は変更できません。']);
        }

        // 最後の管理者を外さない
        if ($user->is_admin && User::where('is_admin', true)->count() <= 1) {
            return back()->withErrors(['admin' => '管理者が0人になるため変更できません。']);
        }

        $user->update(['is_admin' => ! $user->is_admin]);

        return back()->with('status', "{$user->name} を" . ($user->is_admin ? '管理者に' : '一般ユーザーに') . '変更しました。');
    }

    /**
     * パスワードリセットリンクの発行（メール無し運用のため管理者が本人に渡す）。
     * リンクを開いた本人が新パスワードを設定するので、パスワードは誰にも見えない。
     */
    public function issueResetLink(User $user)
    {
        $token = Password::broker()->createToken($user);
        $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

        return back()->with('reset_link', [
            'user' => $user->name,
            'url' => $url,
            'expires' => config('auth.passwords.users.expire', 60),
        ]);
    }
}
