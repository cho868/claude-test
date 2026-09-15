<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Web Push の送信役。
 *
 * ラズパイに優しくするため、送信は「まとめて1回」で行い、
 * 失効した購読(404/410)だけをその場で掃除する（書き込みは実質ゼロ）。
 */
class PushService
{
    /** VAPID鍵が設定されていて送信可能か */
    public function isConfigured(): bool
    {
        return filled(config('services.webpush.public_key'))
            && filled(config('services.webpush.private_key'));
    }

    public function publicKey(): ?string
    {
        return config('services.webpush.public_key');
    }

    /**
     * ユーザーの全端末へ通知を送る。
     *
     * @param  array{title:string,body:string,url?:string,tag?:string}  $payload
     * @return int 送信に成功した端末数
     */
    public function sendToUser(User $user, array $payload): int
    {
        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty() || ! $this->isConfigured()) {
            return 0;
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => config('services.webpush.subject'),
            'publicKey' => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ]]);

        $byEndpoint = $subscriptions->keyBy('endpoint');

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->p256dh,
                    'authToken' => $sub->auth,
                ]),
                json_encode($payload, JSON_UNESCAPED_UNICODE)
            );
        }

        $sent = 0;
        $expired = [];

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;

                continue;
            }

            // 端末側でプッシュを解除済み＝この購読はもう使えないので消す
            if ($report->isSubscriptionExpired()) {
                $expired[] = $byEndpoint[$report->getEndpoint()]->id ?? null;
            }
        }

        $expired = array_filter($expired);

        if ($expired) {
            PushSubscription::whereIn('id', $expired)->delete();
        }

        return $sent;
    }
}
