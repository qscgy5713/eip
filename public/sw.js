/*
 * EIP Service Worker
 *
 * 這是含個資的內部系統,因此刻意只做兩件事:
 *   1. 讓瀏覽器認定這是可安裝的 PWA
 *   2. 網路中斷時,對「頁面導覽」顯示一頁離線提示
 *
 * 不快取任何頁面、API、使用者資料。快取的只有離線提示頁與 PWA 圖示;
 * 登出後裝置上不會留下任何業務資料。
 */
const CACHE = 'eip-offline-v1';
const OFFLINE_URL = '/pwa/offline.html';
const PRECACHE = [OFFLINE_URL, '/pwa/icon-192.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    // 只處理頁面導覽;其餘(含 POST、API、Inertia XHR、靜態檔)一律交給網路,不介入
    if (req.mode !== 'navigate') {
        return;
    }
    event.respondWith(
        fetch(req).catch(() => caches.match(OFFLINE_URL)),
    );
});
