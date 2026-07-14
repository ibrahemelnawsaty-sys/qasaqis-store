# 📚 قصص أطفال — qasaqis.store

متجر كتب أطفال إلكتروني (Laravel 11 + MySQL + Filament v3)، خفيف وسريع، RTL عربي، مصمّم لجمهور مصر.

> ⚠️ **قبل أي تطوير: اقرأ [`AGENTS.md`](AGENTS.md) كاملًا** — الدستور الهندسي الملزم لكل مطوّر/وكيل.

---

## المتطلبات
- PHP **8.2+**
- Composer 2
- MySQL 8 (أو MariaDB 10.4+)
- Node.js 18+ و npm

## التثبيت والتشغيل محليًا
```bash
# 1) تثبيت اعتماديات PHP
composer install

# 2) تجهيز البيئة
cp .env.example .env
php artisan key:generate
#   عدّل بيانات قاعدة البيانات في .env (DB_DATABASE, DB_USERNAME, DB_PASSWORD)

# 3) قاعدة البيانات + البيانات الأولية (الأقسام الستة + دور النشر + 23 كتابًا + الأدوار)
php artisan migrate --seed

# 4) رابط التخزين (لعرض صور الأغلفة وإثباتات الدفع)
php artisan storage:link

# 5) بناء الواجهة الأمامية
npm install
npm run build      # أو npm run dev أثناء التطوير

# 6) التشغيل
php artisan serve
```

- المتجر: `http://localhost:8000`
- لوحة الأدمن: `http://localhost:8000/admin`

### حساب السوبر أدمن الافتراضي (بعد seed)
يُنشأ من `DatabaseSeeder`/`UserSeeder` — راجع بيانات الدخول في ملف الـ seeder. **غيّر كلمة المرور فورًا بعد أول دخول.**

## النشر على Hostinger (ملخص — التفاصيل في `docs/`)
1. ارفع المشروع، واضبط جذر الويب على مجلد `public/`.
2. `composer install --no-dev --optimize-autoloader`
3. اضبط `.env` (APP_ENV=production, APP_DEBUG=false, بيانات MySQL).
4. `php artisan key:generate` (إن لزم) ثم `php artisan migrate --seed --force`
5. `php artisan storage:link`
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
7. `npm ci && npm run build`
8. فعّل SSL واربط الدومين `qasaqis.store` وضع Cloudflare أمامه (كاش/CDN).

## حالة التحقق (شفافية حسب الدستور 1.3 / 1.5)
- ✅ **الواجهة الأمامية:** تُبنى وتُتحقّق عبر `npm run build`.
- ⏳ **زمن تشغيل PHP/MySQL:** يتطلب بيئة PHP 8.2 + MySQL (Hostinger أو محليًا). الترحيلات والاختبارات (`php artisan test`) تُشغَّل هناك.

## الوثائق
- [`AGENTS.md`](AGENTS.md) — الدستور
- [`docs/03-DATABASE-SCHEMA.md`](docs/03-DATABASE-SCHEMA.md) — مخطط قاعدة البيانات
- [`docs/04-ROLES-PERMISSIONS-PAYMENTS.md`](docs/04-ROLES-PERMISSIONS-PAYMENTS.md) — الأدوار والدفع
- [`docs/06-SEARCH-AND-PUBLISHER.md`](docs/06-SEARCH-AND-PUBLISHER.md) — البحث ودار النشر
- [`docs/approved-storefront-design.html`](docs/approved-storefront-design.html) — تصميم الواجهة المعتمد
