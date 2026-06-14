@extends('layouts.app')
@section('title', '予定追加')

@section('content')
<div class="mx-auto max-w-2xl">
    <h2 class="mb-4 text-2xl font-bold">📅 予定追加</h2>
    <form method="POST" action="{{ route('schedule.store') }}" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">タイトル</label>
            <input type="text" name="title" value="{{ old('title') }}" required class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">開始</label>
                <input type="datetime-local" name="starts_at" required class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">終了(任意)</label>
                <input type="datetime-local" name="ends_at" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">場所(任意)</label>
            <input type="text" name="location" value="{{ old('location') }}" placeholder="Discord / オンライン など" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">説明(任意)</label>
            <textarea name="description" rows="3" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">{{ old('description') }}</textarea>
        </div>
        <button class="rounded-lg bg-slate-900 px-5 py-2.5 font-semibold text-white hover:bg-slate-700">作成 (+10pt)</button>
    </form>
</div>
@endsection
