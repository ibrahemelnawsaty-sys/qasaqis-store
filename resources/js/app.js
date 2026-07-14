// ملاحظة أداء: لا نستورد bootstrap.js (axios) — الواجهة لا تحتاج XHR،
// وإبقاء الحزمة خفيفة ضمن ميزانية الأداء (JS ≤ 100KB). Alpine فقط.
import Alpine from 'alpinejs';

/*
 | متجر «قصص أطفال» — منطق الواجهة الخفيف (Alpine.js)
 | 1) الوضع الليلي/النهاري (يتزامن مع سكربت منع الوميض في <head>)
 | 2) قائمة الموبايل + شاشة البحث
 | 3) سلة محلية (localStorage) — واجهة فقط، الطلب النهائي يتم عبر واتساب
 |    (لا توجد طبقة سلة خادمية ضمن هذه المرحلة؛ لا نخترع بيانات خادمية).
 */

const THEME_KEY = 'qasaqis-theme';

// ----- الثيم (Theme) -------------------------------------------------------
function applyTheme(theme) {
    const root = document.documentElement;
    if (theme === 'dark' || theme === 'light') {
        root.setAttribute('data-theme', theme);
    } else {
        root.removeAttribute('data-theme'); // يتبع تفضيل النظام
    }
}

Alpine.store('theme', {
    // 'light' | 'dark' | null(=system)
    value: localStorage.getItem(THEME_KEY),
    get isDark() {
        if (this.value === 'dark') return true;
        if (this.value === 'light') return false;
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    },
    toggle() {
        this.value = this.isDark ? 'light' : 'dark';
        localStorage.setItem(THEME_KEY, this.value);
        applyTheme(this.value);
    },
});

// ----- السلة المحلية (Local cart) -----------------------------------------
const CART_KEY = 'qasaqis-cart';

function readCart() {
    try {
        const raw = localStorage.getItem(CART_KEY);
        const data = raw ? JSON.parse(raw) : [];
        return Array.isArray(data) ? data : [];
    } catch (e) {
        return [];
    }
}

Alpine.store('cart', {
    items: readCart(),
    open: false,
    persist() {
        localStorage.setItem(CART_KEY, JSON.stringify(this.items));
    },
    get count() {
        return this.items.reduce((n, i) => n + (i.qty || 1), 0);
    },
    add(book) {
        if (!book || !book.id) return;
        const found = this.items.find((i) => i.id === book.id);
        if (found) {
            found.qty = (found.qty || 1) + 1;
        } else {
            this.items.push({
                id: book.id,
                title: book.title || '',
                price: book.price || null,
                url: book.url || '#',
                cover: book.cover || '',
                qty: 1,
            });
        }
        this.persist();
        this.open = true;
    },
    remove(id) {
        this.items = this.items.filter((i) => i.id !== id);
        this.persist();
    },
    clear() {
        this.items = [];
        this.persist();
    },
    // يبني رسالة واتساب بكل عناصر السلة لإتمام الطلب يدويًا
    whatsappHref(number) {
        const lines = this.items.map(
            (i) => `• ${i.title}${i.price ? ' — ' + i.price : ''}`
        );
        // نص المقدّمة يأتي من ملفات الترجمة عبر سمة على <html> (لا نص مثبّت في JS).
        const header = document.documentElement.getAttribute('data-wa-order-intro') || '';
        const text = encodeURIComponent(header + '\n' + lines.join('\n'));
        const base = number ? `https://wa.me/${number}` : 'https://wa.me/';
        return `${base}?text=${text}`;
    },
});

// ----- قائمة/بحث الموبايل (root layout data) ------------------------------
Alpine.data('shell', () => ({
    menuOpen: false,
    searchOpen: false,
    closeAll() {
        this.menuOpen = false;
        this.searchOpen = false;
    },
}));

// ----- الاقتراح الفوري للبحث (autocomplete) --------------------------------
// خفيف: يستدعي /search/suggest بعد حرفين مع debounce، ويعرض قائمة مقترحات.
// بلا JS يبقى النموذج يعمل ويُرسِل إلى صفحة نتائج البحث (تحسين تدريجي).
Alpine.data('searchBox', (initial = '') => ({
    q: initial,
    open: false,
    active: -1,
    items: [], // مسطّحة: { label, url, kind }
    endpoint: '',
    init() {
        this.endpoint = this.$root.dataset.suggestUrl || '';
    },
    icon(kind) {
        if (kind === 'book') return '📖';
        if (kind === 'publisher') return '🏷️';
        return '📚';
    },
    async fetchSuggest() {
        const term = this.q.trim();
        if (term.length < 2 || !this.endpoint) {
            this.items = [];
            this.open = false;
            return;
        }
        try {
            const res = await fetch(
                `${this.endpoint}?q=${encodeURIComponent(term)}`,
                { headers: { Accept: 'application/json' } }
            );
            if (!res.ok) {
                this.items = [];
                this.open = false;
                return;
            }
            const data = await res.json();
            const flat = [];
            (data.books || []).forEach((x) => flat.push({ ...x, kind: 'book' }));
            (data.publishers || []).forEach((x) => flat.push({ ...x, kind: 'publisher' }));
            (data.categories || []).forEach((x) => flat.push({ ...x, kind: 'category' }));
            this.items = flat;
            this.active = -1;
            this.open = flat.length > 0;
        } catch (e) {
            this.items = [];
            this.open = false;
        }
    },
    move(dir) {
        if (!this.open || !this.items.length) return;
        this.active = (this.active + dir + this.items.length) % this.items.length;
    },
    onEnter(e) {
        // سهم محدَّد؟ اذهب إليه؛ وإلا اترك النموذج يُرسَل لصفحة النتائج.
        if (this.open && this.active >= 0 && this.items[this.active]) {
            e.preventDefault();
            window.location.href = this.items[this.active].url;
        }
    },
    reopen() {
        if (this.items.length) this.open = true;
    },
    close() {
        this.open = false;
        this.active = -1;
    },
}));

window.Alpine = Alpine;
Alpine.start();
