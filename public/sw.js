/*
 * عامل خدمة قصاقيص — تسريع الزيارات العائدة بأمان.
 *
 * المبدأ: يخزّن الأصول الثابتة المبصومة فقط (immutable: build/media-cache/fonts/images)
 * بنمط stale-while-revalidate — تُخدَم فورًا من الكاش ثم تُحدَّث بالخلفية. أمّا صفحات
 * HTML (التنقّل) و/media (توليد) والطلبات ذات الحالة (سلة/دفع/POST) فتمرّ للشبكة دائمًا
 * فلا يُخدَم محتوًى قديم أو خاصّ بمستخدم آخر. آمن: يتخطّى أي شيء خارج القائمة البيضاء.
 */
const CACHE = 'qsq-static-v1';

// أصول ثابتة مبصومة/طويلة الكاش فقط — آمنة للتخزين الدائم (رابط جديد لكل تغيير).
const STATIC_RE = /\/(build|media-cache|fonts|images)\//;

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keys = await caches.keys();
            await Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)));
            await self.clients.claim();
        })(),
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;

    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    if (url.origin !== self.location.origin) return; // أصل خارجيّ — لا نتدخّل
    if (req.mode === 'navigate') return;             // HTML دائمًا من الشبكة (لا نخزّن صفحات)
    if (!STATIC_RE.test(url.pathname)) return;        // أصول ثابتة مبصومة فقط

    event.respondWith(
        (async () => {
            const cache = await caches.open(CACHE);
            const cached = await cache.match(req);

            const network = fetch(req)
                .then((res) => {
                    // نخزّن استجابة أساسيّة ناجحة فقط (لا أخطاء ولا opaque).
                    if (res && res.status === 200 && res.type === 'basic') {
                        cache.put(req, res.clone());
                    }
                    return res;
                })
                .catch(() => cached);

            // stale-while-revalidate: الكاش فورًا إن وُجد، وإلا الشبكة.
            return cached || network;
        })(),
    );
});
