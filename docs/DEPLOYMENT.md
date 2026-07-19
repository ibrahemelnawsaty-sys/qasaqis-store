# 🚀 دليل النشر — «قصص أطفال» (qasaqis.store)

> **قبل أي شيء: اقرأ [`AGENTS.md`](../AGENTS.md).** هذا الدليل عملي وموجّه للنشر على **Hostinger + Cloudflare**.
>
> **حالة التحقق (الدستور 1.3 / 1.5):** هذا الدليل مكتوب من قراءة ملفات المستودع فعليًا (`composer.json`، `.env.example`، `AdminPanelProvider`، الـ seeders، `BookResource`). أوامر `artisan`/`composer`/`npm` **لم تُشغَّل** هنا لعدم توفّر PHP في بيئة الكتابة؛ تُنفَّذ على الخادم كما هو موضّح. لا تدّعِ نجاح خطوة قبل مشاهدة مخرجها فعليًا على الخادم.

---

## 0. المكدّس والحقائق المؤكدة من المستودع

| العنصر | القيمة المؤكدة | المصدر |
|---|---|---|
| PHP | `^8.2` | `composer.json` |
| Laravel | `^11.31` | `composer.json` |
| Filament | `^3.2` | `composer.json` |
| الصلاحيات | `spatie/laravel-permission ^6.9` | `composer.json` |
| مسار لوحة الأدمن | `/admin` (مع صفحة دخول) | `app/Providers/Filament/AdminPanelProvider.php` |
| قاعدة البيانات | MySQL 8 (أو MariaDB 10.4+) | `.env.example` / `README.md` |
| الكاش/الطابور/الجلسة (افتراضيًا) | `database` | `.env.example` |
| بناء الأصول | Vite (`npm run build`) | `package.json` / `vite.config.js` |
| قرص أغلفة الكتب | `public` ← مجلد `books/covers` | `app/Filament/Resources/BookResource.php` |

> ⚠️ **لا يوجد أمر `php artisan books:import-images` في هذا المستودع** (لا يوجد `app/Console/Commands`). استيراد الأغلفة يتم **من لوحة الأدمن** فقط — انظر القسم 8.

---

## 1. متطلبات Hostinger

اختر خطة **Business / Cloud** التي توفّر:

- **PHP 8.2 أو أحدث** (فعّله من hPanel ← Advanced ← PHP Configuration، وفعّل الامتدادات: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`, `gd` أو `imagick`).
- **MySQL 8** (قاعدة بيانات + مستخدم مخصص).
- **وصول SSH** + **Composer 2** (متوفّر على خطط Business/Cloud).
- **إمكانية ضبط جذر الويب (Document Root) على مجلد `public/`** — أساسي للأمان.
- **Node.js 18+ و npm** لبناء الأصول على الخادم — أو، إن لم يتوفّر Node على الاستضافة المشتركة، **ابنِ الأصول محليًا وارفع مجلد `public/build` جاهزًا** (انظر القسم 5).
- **شهادة SSL مجانية (Let's Encrypt)** من hPanel.
- **Cron** (لتشغيل الطابور/الجدولة إن لزم لاحقًا).

---

## 2. رفع الملفات وضبط جذر الويب

1. ارفع المشروع عبر **Git** (`git clone` على الخادم عبر SSH — الأنظف) أو عبر **File Manager/FTP**.
   - **لا ترفع** `vendor/`، `node_modules/`، `.env`، `.git/` (كلها في `.gitignore`).
2. **اضبط جذر الويب (Document Root) على `.../qasaqis-store/public`** — لا على جذر المشروع.
   - إن كانت الاستضافة تجبرك على `public_html`، فالخيار الموصى به: ضع المشروع خارج `public_html` واجعل `public_html` رابطًا رمزيًا (symlink) لـ `public`:
     ```bash
     ln -s /home/USER/qasaqis-store/public /home/USER/public_html
     ```
   - أو عدّل جذر الويب من hPanel ليشير مباشرة إلى مجلد `public`.
3. تأكد من صلاحيات الكتابة على مجلدي الرفع:
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```

> **أمان (الدستور 4.5 / 4.7):** إتاحة جذر المشروع كاملًا للويب تكشف `.env` و`storage`. جذر الويب **يجب** أن يكون `public/` حصريًا.

---

## 3. تثبيت الاعتماديات (Production)

عبر SSH داخل مجلد المشروع:

```bash
composer install --no-dev --optimize-autoloader
# مكافئ لـ:  composer install --no-dev -o
```

> `--no-dev` يستبعد أدوات التطوير (Pint/PHPUnit/Pail…)، و`-o` يولّد classmap مُحسّن (الدستور 5 — أداء).

---

## 4. تهيئة البيئة والأسرار (`.env`)

```bash
cp .env.example .env
php artisan key:generate
```

ثم عدّل `.env` بقيم الإنتاج. **الحد الأدنى الإلزامي:**

```dotenv
APP_ENV=production
APP_DEBUG=false                 # إلزامي في الإنتاج (الدستور 4.7 / ممنوع 9)
APP_URL=https://qasaqis.store
APP_LOCALE=ar
APP_TIMEZONE=Africa/Cairo

# قاعدة بيانات Hostinger (من hPanel ← Databases)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1               # أو المضيف الذي يعطيه Hostinger
DB_PORT=3306
DB_DATABASE=اسم_القاعدة
DB_USERNAME=مستخدم_القاعدة
DB_PASSWORD=كلمة_مرور_قوية

# على الاستضافة المشتركة (بلا Redis) أبقِها database
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp               # اضبط SMTP الحقيقي بدل log
MAIL_FROM_ADDRESS="hello@qasaqis.store"
```

> **الأسرار (الدستور 4.3 / ممنوع 9):** كل المفاتيح تعيش في `.env` فقط، لا في Git إطلاقًا. `APP_KEY` يجب أن يكون مولّدًا. لا تترك `APP_DEBUG=true`.

---

## 5. قاعدة البيانات والبيانات الأولية

### 5.1 إنشاء قاعدة MySQL
من hPanel ← **Databases ← MySQL Databases**: أنشئ القاعدة والمستخدم وامنحه كل الصلاحيات، وضع نفس القيم في `.env`.

### 5.2 (موصى به) ضبط كلمة مرور السوبر أدمن قبل الـ seed
الـ `UserSeeder` يقرأ كلمة المرور من متغيّر بيئة `SEED_SUPER_ADMIN_PASSWORD` إن وُجد، وإلا يستخدم قيمة **placeholder موثّقة (غير سرية)**. أضِف قبل الـ seed:

```dotenv
SEED_SUPER_ADMIN_PASSWORD='ضع_كلمة_مرور_قوية_ومؤقتة_هنا'
```

> هذا المتغيّر **غير موجود في `.env.example`** لكنه مقروء فعليًا في `database/seeders/UserSeeder.php` — أضِفه يدويًا. (تفاصيل الحسابات في القسم 10.)

### 5.3 الترحيل والبذور
```bash
php artisan migrate --seed --force
```

- `--force` مطلوب لأن البيئة `production`.
- الـ seeders تنشئ: الأقسام الستة، دور النشر، **23 كتابًا** (الدستور 0.3)، الأدوار/الصلاحيات (spatie)، طرق الدفع، والإعدادات.
- **BOOK1 «أنا لستُ شقيًا!»** يبقى **بلا سعر**، و**BOOK10 «هون عليك»** يبقى **بلا غلاف** — هذا سلوك مقصود (الدستور 0.4)؛ يستكملهما الأدمن لاحقًا. لا تخترع قيمًا.
- **ممنوع** تشغيل `migrate:fresh` / `migrate:refresh` / `db:wipe` على الإنتاج (الدستور 3.3).
- **ممنوع** تشغيل `migrate:rollback` المجرّد على الإنتاج: يُرجع الدفعة كاملة لا آخر
  هجرة، فيحذف جداول محتوى الأدمن ويُسقط أعمدة يكتبها الكود الجاري. التفصيل والبديل
  في §12.

### 5.4 رابط التخزين
```bash
php artisan storage:link
```
ينشئ `public/storage → storage/app/public` لعرض أغلفة الكتب (قرص `public`، مجلد `books/covers`). إثباتات الدفع تبقى محمية خارج المسار العام (الدستور 4.5).

---

## 6. بناء الأصول وأصول Filament

### 6.1 أصول Filament (مطلوبة)
```bash
php artisan filament:assets
```
تنشر/تحدّث أصول لوحة الأدمن (CSS/JS/الخطوط). شغّلها بعد كل تحديث لـ Filament أو الحِزم (يوازيها في `composer.json` سكربت `post-update-cmd` الذي ينشر `laravel-assets`).

### 6.2 بناء واجهة المتجر (Vite)
**الخيار أ — البناء على الخادم (إن توفّر Node):**
```bash
npm ci
npm run build      # ينتج public/build
```

**الخيار ب — البناء محليًا ورفع الناتج (إن لم يتوفّر Node على الاستضافة):**
```bash
# محليًا:
npm ci && npm run build
# ثم ارفع مجلد public/build كاملًا إلى نفس المسار على الخادم.
```

> `npm ci` (وليس `install`) لتثبيت مطابق لـ `package-lock.json`. الأصول الناتجة versioned للاستفادة من كاش المتصفح/Cloudflare (الدستور 5.4).

### 6.3 كاش الإنتاج (بعد ضبط `.env` نهائيًا)
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> **مهم:** أعد توليد هذا الكاش بعد **أي** تعديل على `.env` أو المسارات، وإلا تُقرأ القيم القديمة. لمسح الكاش: `php artisan optimize:clear`.

---

## 7. الدومين + SSL + Cloudflare

### 7.1 ربط الدومين
- وجّه `qasaqis.store` و`www.qasaqis.store` إلى الاستضافة (Nameservers أو سجلّات A).
- من hPanel فعّل **SSL (Let's Encrypt)** و**Force HTTPS** (يوازي إجبار HTTPS في الدستور 4.7).

### 7.2 Cloudflare (مجاني) — إعدادات مقترحة
- **SSL/TLS mode:** `Full (Strict)` (بعد تفعيل SSL على الأصل).
- **Always Use HTTPS:** On، **Automatic HTTPS Rewrites:** On.
- **Brotli:** On (ضغط أخف للجمهور المصري بطيء الشبكة — الدستور 1.6/5).
- **Auto Minify / Speed:** فعّل تحسينات JS/CSS/HTML المتاحة بحذر واختبرها.
- **Caching level:** Standard؛ و**Cache Rule / Page Rule** لتخزين الأصول الساكنة `*/build/*` و`*/storage/*` بعمر طويل (versioned assets آمنة).
- **لا تُخزّن (Bypass Cache)** مسارات: `/admin/*`, وأي مسار دفع/جلسة/POST، لتجنّب تسريب جلسات.
- فعّل **HTTP/2 / HTTP/3** و**0-RTT** إن توفّرت.
- بعد تفعيل Cloudflare أمام Laravel: تأكد من الثقة بالبروكسي لقراءة IP الحقيقي (`TrustProxies`) إن لزم لسجلّات الأمان.

---

## 8. أغلفة الكتب (استيراد الصور)

> **لا يوجد أمر `php artisan books:import-images` في المستودع.** رفع الأغلفة يتم **حصريًا من لوحة الأدمن**.

- ادخل `/admin` ← **الكتب (Books)** ← افتح الكتاب ← حقل **غلاف الكتاب (`cover_image`)** يرفع إلى قرص `public` داخل `books/covers` (من `BookResource.php`).
- الصور المصدرية موجودة في المستودع تحت `database/seed/BOOK/BOOK*/…` كمرجع بصري؛ ارفع المناسب منها لكل كتاب من نفس الشاشة.
- **BOOK10 «هون عليك»** بلا غلاف على القرص — يُعرض بعنصر بديل محايد حتى يرفع الأدمن غلافه. **ممنوع** توليد/اختلاق غلاف (الدستور 0.4 / ممنوع 22).
- ضمن ميزانية الأداء: قدّم الأغلفة مضغوطة WebP بأحجام مناسبة (الدستور 5.3).

---

## 9. تفعيل طرق الدفع

طرق الدفع تُدار من **لوحة الأدمن** (زُرِعت مبدئيًا عبر `PaymentMethodSeeder`، وتفاصيلها الحقيقية يملؤها الأدمن — الدستور 4.3):

### 9.1 الدفع اليدوي و COD
من `/admin` ← **طرق الدفع**: فعّل **إنستاباي / فودافون كاش / التحويل البنكي** وأدخل بيانات الحساب/التعليمات، وفعّل **الدفع عند الاستلام (COD)**. لا أسرار في الكود.

### 9.2 الدفع الأونلاين (بوابة مصرية)
في `.env` (المفاتيح موجودة فارغة في `.env.example`):

```dotenv
ONLINE_PAYMENT_ENABLED=false      # يبقى false حتى تتوفّر مفاتيح فعّالة
PAYMENT_GATEWAY=paymob            # paymob أو kashier

# Paymob
PAYMOB_API_KEY=
PAYMOB_INTEGRATION_ID=
PAYMOB_IFRAME_ID=
PAYMOB_HMAC_SECRET=

# Kashier
KASHIER_MERCHANT_ID=
KASHIER_API_KEY=
KASHIER_SECRET_KEY=
```

- **إن لم تتوفّر مفاتيح فعّالة، أبقِ `ONLINE_PAYMENT_ENABLED=false`** — عندها يُخفى «الدفع الأونلاين» ويُعرض «الدفع الأونلاين مغلق حاليًا» (سلوك موثّق في `.env.example`).
- عند توفّر المفاتيح: املأها، اضبط `ONLINE_PAYMENT_ENABLED=true`، ثم `php artisan config:cache`.

---

## 10. بيانات السوبر أدمن وتغيير كلمة المرور

من `database/seeders/UserSeeder.php` (مؤكد):

| الاسم | البريد (اسم الدخول) | الدور |
|---|---|---|
| المالك | `owner@qasaqis.store` | `super_admin` |
| مدير النظام | `admin@qasaqis.store` | `super_admin` |

- **كلمة المرور:** قيمة `SEED_SUPER_ADMIN_PASSWORD` من `.env` إن ضُبطت (القسم 5.2)، وإلا القيمة الاحتياطية الموثّقة **غير السرية** `Qasaqis@Change-Me-2026`.
- **إلزامي:** بعد أول دخول على `/admin`، **غيّر كلمة المرور فورًا** من صفحة الملف الشخصي، و**احذف/عطّل** المتغيّر `SEED_SUPER_ADMIN_PASSWORD` من `.env`.
- الدخول: `https://qasaqis.store/admin`.

---

## 11. ✅ قائمة تحقق ما قبل الإطلاق

### الأمان
- [ ] `APP_DEBUG=false` و`APP_ENV=production`.
- [ ] `APP_KEY` مولّد، ولا أسرار في Git (الدستور 4.3).
- [ ] جذر الويب على `public/` فقط؛ `.env` و`storage` غير مكشوفين.
- [ ] كلمة مرور السوبر أدمن غُيّرت، و`SEED_SUPER_ADMIN_PASSWORD` أُزيل.
- [ ] HTTPS مفروض + SSL/TLS `Full (Strict)` على Cloudflare.
- [ ] `/admin/*` ومسارات الدفع خارج كاش Cloudflare.
- [ ] رفع إثباتات الدفع محمي (jpg/png/pdf، خارج المسار العام — الدستور 4.5).
- [ ] **نسخة احتياطية** حديثة للقاعدة وملفات الرفع قبل الإطلاق، مع خطة نسخ دوري والتحقق من الاسترجاع (الدستور 3.4).

### الأداء
- [ ] `config:cache` + `route:cache` + `view:cache` مفعّلة.
- [ ] `npm run build` منفّذ / `public/build` مرفوع.
- [ ] `php artisan filament:assets` منفّذ.
- [ ] Brotli/HTTP2/3 مفعّلة على Cloudflare، والأصول الساكنة مخزّنة كاش طويل.
- [ ] قياس فعلي (Lighthouse/PageSpeed) ضمن ميزانية الدستور (LCP ≤ 2.5s، JS ≤ 100KB — الباب 5).

### المحتوى وSEO
- [ ] الأقسام الستة كلها ظاهرة (حتى الفارغة — الدستور 0.3).
- [ ] 23 كتابًا موجودة؛ BOOK1 بلا سعر وBOOK10 بلا غلاف مُعالجان بلا اختراع.
- [ ] الأغلفة مرفوعة من لوحة الأدمن ومضغوطة WebP.
- [ ] عناوين/أوصاف SEO لكل صفحة عبر CMS، `sitemap`/`robots`، بيانات Open Graph.
- [ ] `MAIL_*` حقيقي ويُرسل، ومفاتيح الدفع مضبوطة أو الأونلاين مغلق بوضوح.

---

## 12. أوامر الصيانة السريعة

```bash
# بعد أي تحديث كود على الخادم:
php artisan down --retry=60     # ← إلزامي، انظر التحذير أدناه
mysqldump -u USER -p DBNAME > ~/backups/qasaqis-$(date +%F-%H%M).sql
git pull
composer install --no-dev -o
php artisan migrate --force
php artisan filament:assets
npm ci && npm run build        # أو ارفع public/build
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

> ### ⚠ لماذا `down` إلزامي وليس ترفًا
>
> بين `git pull` و`migrate` يعمل **كود جديد على مخطّط قاعدة قديم**. هذه ليست
> نافذة نظرية: `PlaceOrderAction::findReplay()` يستعلم عن `orders.idempotency_key`
> **قبل** فتح المعاملة، فإن لم تكن الهجرة قد جرت بعد يسقط الاستعلام بـ
> `SQLSTATE[42S22]` وتحصل كل عميلة تضغط «تأكيد الطلب» على خطأ 500.
> ولا يُنشأ طلب ولا يُحجز مخزون — تفقد البيع بلا أثر.
>
> والأخبث أن الرئيسية والتنقّل يظلّان سليمين تمامًا (استعلامات المحتوى ملفوفة
> بـ `rescue()`)، فلا شيء ينبّهك أن الشراء متوقّف. مهما قصُرت النافذة فهي مفتوحة،
> ولا يمكن ضمان ألّا تتأخّر يدك أو ينقطع اتصال SSH بين الأمرين.
>
> إن احتجت الوصول للوحة أثناء النشر: `php artisan down --secret=...` ثم ادخل
> عبر الرابط السرّي.

> **تحذير: `migrate:rollback` بعد نشر فيه عدّة هجرات.** الهجرات المنشورة معًا
> تحمل رقم دفعة واحدًا، و`migrate:rollback` المجرّد يُرجعها **كلها** لا آخرها.
> في دفعة تموز/يوليو ٢٠٢٦ مثلًا كان ذلك يعني حذف `trust_items` و`why_items`
> و`feedback_images` **بكل ما حرّره الأدمن أو أضافه بعد النشر**، وتفريغ ربط
> السلاسل عن الكتب، وإسقاط `orders.idempotency_key` فينكسر الدفع فورًا لأن
> الكود ما زال يكتبه.
>
> **الخلل في الواجهة يُصلَح من لوحة الأدمن أو بـ SQL، لا بالتراجع عن الهجرات:**
>
> ```sql
> UPDATE why_items   SET is_active = 0 WHERE id = ?;   -- إخفاء بطاقة معطوبة
> UPDATE trust_items SET is_active = 0 WHERE id = ?;
> ```
>
> وإن لزم تراجع فعلي: `mysqldump` أولًا، ثم `migrate:rollback --step=1 --force` فقط.
> ولتقليل نصف قطر الانفجار مسبقًا، شغّل `migrate --step --force` فيأخذ كلُّ هجرة
> دفعةً مستقلّة، فيقتصر أي تراجع متسرّع على آخر واحدة.

> **تذكير الدستور 1.3:** لا تُعلن نجاح النشر قبل فتح `https://qasaqis.store` و`/admin` فعليًا ومشاهدة عملهما.

---

*نهاية دليل النشر — «قصص أطفال» · `qasaqis.store`.*
