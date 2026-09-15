<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ブラウザ(Service Worker)からの Web Push 購読を受け取る。
 *
 * iOS はホーム画面に追加した PWA からでないと購読できない（iOS 16.4+ の仕様）。
 * その案内は画面側で出している。
 */
class PushController extends Controller
{
    public function subscribe(Request $request, PushService $push): JsonResponse
    {
        abort_unless($push->isConfigured(), 503, 'サーバー側でVAPID鍵が未設定です。');

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000', 'url'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:80'],
        ]);

        // 同じ端末から再購読された場合は上書き（行を増やさない）
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashFor($data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'label' => $data['label'] ?? null,
            ]
        );

        return response()->json([
            'ok' => true,
            'count' => $request->user()->pushSubscriptions()->count(),
        ]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);

        // 自分の購読だけを消す（他人の endpoint を投げられても消えない）
        $request->user()->pushSubscriptions()
            ->where('endpoint_hash', PushSubscription::hashFor($data['endpoint']))
            ->delete();

        return response()->json([
            'ok' => true,
            'count' => $request->user()->pushSubscriptions()->count(),
        ]);
    }

    /** 届くかどうかの確認用。 */
    public function test(Request $request, PushService $push): JsonResponse
    {
        $sent = $push->sendToUser($request->user(), [
            'title' => '🔔 テスト通知',
            'body' => 'この通知が見えていれば設定は完了です。',
            'url' => route('social.index'),
            'tag' => 'routine-test',
        ]);

        return response()->json(['ok' => $sent > 0, 'sent' => $sent]);
    }
}
