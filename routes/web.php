<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
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
    Route::post('register', [RegisteredUserController::class, 'store']);
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
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
