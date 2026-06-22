<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FitnessController;
use App\Http\Controllers\MatchRecordController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\MemoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScheduleEventController;
use App\Http\Controllers\SleepRecordController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\TierListController;
use App\Http\Controllers\TournamentController;
use Illuminate\Support\Facades\Route;

// 未ログインはログイン画面へ
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

/*
 * 認証(ゲスト向け)
 */
Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    // 1分あたり6回までに制限（総当たり対策）
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1');
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');
});

/*
 * 認証済みユーザー向け
 */
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // プロフィール(Discord / Steam 連携 ID 登録)
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    // 資料 / ナレッジ(Markdown 記事)
    Route::resource('documents', DocumentController::class);

    // フィットネス(体重・運動の記録と可視化)
    Route::get('fitness', [FitnessController::class, 'index'])->name('fitness.index');
    Route::post('fitness/goal', [FitnessController::class, 'updateGoal'])->name('fitness.goal');
    Route::post('fitness/weight', [FitnessController::class, 'storeWeight'])->name('fitness.weight.store');
    Route::post('fitness/exercise', [FitnessController::class, 'storeExercise'])->name('fitness.exercise.store');
    Route::delete('fitness/weight/{weight}', [FitnessController::class, 'destroyWeight'])->name('fitness.weight.destroy');
    Route::delete('fitness/exercise/{exercise}', [FitnessController::class, 'destroyExercise'])->name('fitness.exercise.destroy');

    // チャレンジ(期間を決めて登録者と競う)
    Route::resource('challenges', ChallengeController::class)->except(['edit', 'update']);
    Route::post('challenges/{challenge}/join', [ChallengeController::class, 'join'])->name('challenges.join');
    Route::post('challenges/{challenge}/leave', [ChallengeController::class, 'leave'])->name('challenges.leave');

    // 対戦ゲームの戦績(手動)
    Route::get('matches', [MatchRecordController::class, 'index'])->name('matches.index');
    Route::post('matches', [MatchRecordController::class, 'store'])->name('matches.store');
    Route::delete('matches/{match}', [MatchRecordController::class, 'destroy'])->name('matches.destroy');

    // 管理者エリア
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('server', [AdminController::class, 'server'])->name('server');
        Route::post('tasks/{task}/toggle', [AdminController::class, 'toggleTask'])->name('tasks.toggle');
        Route::get('users', [AdminController::class, 'users'])->name('users');
        Route::post('users/{user}/toggle-admin', [AdminController::class, 'toggleAdmin'])->name('users.toggle-admin');
    });

    // トーナメント作成ツール
    Route::resource('tournaments', TournamentController::class)
        ->except(['edit']);

    // ソート / ランキング(ティアリスト)作成ツール
    Route::resource('tierlists', TierListController::class);

    // GMQ2 メモ等
    Route::get('memos', [MemoController::class, 'index'])->name('memos.index');
    Route::post('memos', [MemoController::class, 'store'])->name('memos.store');
    Route::put('memos/{memo}', [MemoController::class, 'update'])->name('memos.update');
    Route::delete('memos/{memo}', [MemoController::class, 'destroy'])->name('memos.destroy');

    // 睡眠時間チェック
    Route::get('sleep', [SleepRecordController::class, 'index'])->name('sleep.index');
    Route::post('sleep', [SleepRecordController::class, 'store'])->name('sleep.store');
    Route::delete('sleep/{sleep}', [SleepRecordController::class, 'destroy'])->name('sleep.destroy');

    // アンケート
    Route::resource('surveys', SurveyController::class)
        ->except(['edit', 'update']);
    Route::post('surveys/{survey}/vote', [SurveyController::class, 'vote'])->name('surveys.vote');
    Route::post('surveys/{survey}/close', [SurveyController::class, 'close'])->name('surveys.close');

    // スケジュール共有
    Route::resource('schedule', ScheduleEventController::class)
        ->parameters(['schedule' => 'schedule'])
        ->except(['edit', 'update']);
    Route::post('schedule/{schedule}/attend', [ScheduleEventController::class, 'attend'])->name('schedule.attend');

    // ゲーム時間(Discord / Steam 連携 + 手動)
    Route::get('games', [GameSessionController::class, 'index'])->name('games.index');
    Route::post('games', [GameSessionController::class, 'store'])->name('games.store');
    Route::post('games/sync-steam', [GameSessionController::class, 'syncSteam'])->name('games.sync-steam');
    Route::delete('games/{game}', [GameSessionController::class, 'destroy'])->name('games.destroy');
});
