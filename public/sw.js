/**
 * 身内ポータルの Service Worker。
 *
 * 役割は2つだけ。欲張らない。
 *   1. Web Push を受け取って通知を出す（これが無いと定時通知ができない）
 *   2. オフライン時にエラー画面ではなく案内ページを出す
 *
 * ログイン後のHTMLはキャッシュしない（古い内容が出たり、
 * 端末に残ったりするのを避けるため）。
 */
const CACHE = 'portal-shell-v1';
const SHELL = ['/offline.html', '/icons/icon-192.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;

    if (req.method !== 'GET') return;

    // アイコンだけはキャッシュ優先（通知に毎回使うため）
    if (new URL(req.url).pathname.startsWith('/icons/')) {
        event.respondWith(caches.match(req).then((hit) => hit || fetch(req)));
        return;
    }

    // ページ遷移が失敗した時だけオフライン案内に差し替える
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    }
});

self.addEventListener('push', (event) => {
    let data = { title: '身内ポータル', body: '', url: '/dashboard' };

    try {
        if (event.data) data = { ...data, ...event.data.json() };
    } catch (e) {
        if (event.data) data.body = event.data.text();
    }

    event.waitUntil(self.registration.showNotification(data.title, {
        body: data.body,
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        tag: data.tag || 'portal',
        renotify: true,
        data: { url: data.url || '/dashboard' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/dashboard';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            // すでに開いているウィンドウがあればそれを使う（無駄にタブを増やさない）
            for (const client of clients) {
                if ('focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            return self.clients.openWindow(url);
        })
    );
});
