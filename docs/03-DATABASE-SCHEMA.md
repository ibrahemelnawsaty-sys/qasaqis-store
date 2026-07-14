<div dir="rtl" align="right">

# مخطط قاعدة البيانات MySQL (Database Schema / ERD)

| | |
|---|---|
| **المشروع** | منصة قصص أطفال — `qasaqis.store` |
| **نوع المستند** | مخطط قاعدة البيانات (Database Schema / ERD) — مرجع تقني للتنفيذ |
| **رقم المستند** | 03 |
| **الحزمة التقنية** | Laravel 11 (PHP 8.2+) · MySQL 8 · Filament v3 · spatie/laravel-permission · Blade + Tailwind + Alpine.js |
| **الاستضافة** | Hostinger (استضافة مشتركة) + Cloudflare مجاني |

> **ملاحظة تسمية موحّدة:** جدول الكتب في هذا المخطط اسمه `products` (كل «كتاب» = سجل في `products`)، وعمود العنوان اسمه `title`. أي إشارة إلى «books» في المقتطفات البرمجية المدمجة تُقرأ كـ `products`. عمود ربط الناشر هو `products.publisher_id`.

---

## 0) مبادئ عامة على مستوى المحرك (Engine Conventions)

- **المحرك:** كل الجداول `InnoDB` لدعم المفاتيح الأجنبية والمعاملات (Transactions).
- **الترميز:** `utf8mb4` / `utf8mb4_unicode_ci` على مستوى الجداول والأعمدة — دعم كامل للعربية والإيموجي.
- **المفاتيح الأساسية:** `BIGINT UNSIGNED AUTO_INCREMENT` (عبر `$table->id()`).
- **التواريخ:** `TIMESTAMP NULL` عبر `timestamps()`؛ والحذف الناعم `SoftDeletes` (`deleted_at`) حيث يُذكر صراحةً.
- **الأموال:** تُخزَّن دائمًا كـ `DECIMAL(10,2)` بالجنيه المصري — **لا `FLOAT`/`DOUBLE`** للمبالغ إطلاقًا.
- **حد المفتاح المفهرس:** أعمدة `VARCHAR` المفهرسة (`email`, `slug`) بطول ≤ **191** لتفادي حد مفتاح `utf8mb4` (767 بايت) على إعدادات MySQL القديمة في الاستضافة المشتركة، أو الاعتماد على `ROW_FORMAT=DYNAMIC` (افتراضي في 8.0).

> **صندوق ملاحظة — أداء الاستضافة المشتركة:** قلّل الفهارس المركّبة الزائدة (كل فهرس يبطّئ الكتابة ويكبّر الجدول)، تجنّب `SELECT *` واعتمد Eager Loading لتفادي مشكلة N+1، واجعل الأعمدة الطويلة نادرة الاستعلام (`LONGTEXT` / HTML) بلا فهرسة.

---

## خريطة المجموعات (Table Groups)

| # | المجموعة | الجداول الرئيسية |
|---|---|---|
| 1 | المستخدمون والصلاحيات | `users`, Spatie tables, `support_user_products` |
| 2 | الأقسام والناشرون والمنتجات | `categories`, `publishers`, `products`, `product_category`, `media` |
| 3 | فهرس البحث المطبّع | `search_index` (+ FULLTEXT) |
| 4 | الطلبات والدفع | `orders`, `order_items`, `order_status_history`, `payment_methods`, `payments`, `payment_proofs` |
| 5 | الكوبونات | `coupons`, `coupon_product`, `coupon_category`, `coupon_usages` |
| 6 | إدارة المحتوى CMS | `pages`, `content_blocks`, `banners`, `menus`, `menu_items` |
| 7 | البوب أب والاستبيانات | `popups`, `surveys`, `survey_questions`, `survey_responses`, `survey_answers` |
| 8 | الـ SEO (Polymorphic) | `seo_meta` |
| 9 | المراجعات والاستفسارات | `reviews`, `inquiries`, `faqs` |
| 10 | الإعدادات والشحن والتدقيق والبنية التحتية | `settings`, `governorates`, `shipping_rates`, `activity_log`, جداول database driver |

---

## المجموعة 1: المستخدمون والصلاحيات (Users & Permissions)

### `users`

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `name` | VARCHAR(150) | |
| `email` | VARCHAR(191) | **UNIQUE** |
| `email_verified_at` | TIMESTAMP NULL | |
| `phone` | VARCHAR(20) NULL | INDEX — للبحث والدعم |
| `password` | VARCHAR(255) | |
| `category_id` | BIGINT UNSIGNED NULL | FK → `categories.id` — لتقييد الدعم بقسمه (نطاق افتراضي) |
| `is_active` | BOOLEAN default 1 | INDEX |
| `avatar_path` | VARCHAR(255) NULL | |
| `last_login_at` | TIMESTAMP NULL | |
| `remember_token` | VARCHAR(100) NULL | |
| `timestamps` | | |
| `deleted_at` | TIMESTAMP NULL | SoftDeletes |

**الفهارس:** `UNIQUE(email)` · `INDEX(phone)` · `INDEX(category_id)` · `INDEX(is_active)`.

### جداول Spatie (تُنشأ عبر migration الحزمة)

- `roles` (id, `name`, `guard_name`, timestamps) — `UNIQUE(name, guard_name)`.
- `permissions` (id, `name`, `guard_name`, timestamps) — `UNIQUE(name, guard_name)`.
- `model_has_roles` (role_id, model_type, model_id) — PK مركّب + `INDEX(model_id, model_type)`.
- `model_has_permissions` (permission_id, model_type, model_id) — نفس النمط.
- `role_has_permissions` (permission_id, role_id) — PK مركّب.

**الأدوار (Roles):** `super_admin` (كل شيء)، `admin` (أقل)، `it` (تقني/إعدادات/مفاتيح API)، `support` (تعليقات/استفسارات مقيّدة بمنتجات محددة)، `content_editor` (محرر محتوى)، `order_manager` (مسؤول طلبات)، `marketer` (مسوّق).
الصلاحيات بنمط `{action}_{resource}`. `super_admin` تجاوز شامل عبر `Gate::before`.

### `support_user_products` — تقييد الدعم بمنتجات/أقسام محددة

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `user_id` | BIGINT UNSIGNED | FK → `users.id` (cascade) |
| `product_id` | BIGINT UNSIGNED NULL | FK → `products.id` — تقييد على مستوى منتج واحد |
| `category_id` | BIGINT UNSIGNED NULL | FK → `categories.id` — تقييد على مستوى قسم كامل |
| `timestamps` | | |

**الفهارس:** `INDEX(user_id)` · `INDEX(product_id)` · `INDEX(category_id)` · `UNIQUE(user_id, product_id)` · `UNIQUE(user_id, category_id)`.
**المنطق:** صف بـ `product_id` = وصول لمنتج واحد؛ صف بـ `category_id` = وصول لكل القسم. تُفرَض عبر Policy تجمع بين الصلاحية والنطاق.

---

## المجموعة 2: الأقسام والناشرون والمنتجات

### `categories` — الأقسام الستة (مع دعم أقسام فرعية مستقبلًا)

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `parent_id` | BIGINT UNSIGNED NULL | FK → `categories.id` (self, set null) — للأقسام الفرعية |
| `name` | VARCHAR(120) | |
| `slug` | VARCHAR(140) | **UNIQUE** |
| `description` | TEXT NULL | |
| `icon` | VARCHAR(100) NULL | أيقونة/إيموجي القسم |
| `image_path` | VARCHAR(255) NULL | |
| `color_hex` | VARCHAR(7) NULL | من هوية العلامة (بنفسجي/ذهبي/برتقالي/وردي) |
| `sort_order` | INT default 0 | INDEX |
| `is_active` | BOOLEAN default 1 | INDEX |
| `timestamps` | | |

**الفهارس:** `UNIQUE(slug)` · `INDEX(parent_id)` · `INDEX(sort_order)` · `INDEX(is_active)`.

> **البيانات الأولية (Seeder) — الأقسام الستة تبقى كلها حتى الفارغة حاليًا:** سلوكيات ومشاعر (14 كتابًا)، كتب علمية (5)، قصص (3)، كتب دينية (1)، روايات (0)، كتب طفولة مبكرة (0).

### `publishers` — كيان دار النشر (Publisher) 🆕

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `name` | VARCHAR(190) | اسم الناشر |
| `slug` | VARCHAR(190) | **UNIQUE** — مُولّد من الاسم |
| `name_normalized` | VARCHAR(190) NULL | نسخة مطبّعة عربيًا لتسريع بحث الناشر (تُملأ عبر Observer) |
| `description` | TEXT NULL | نبذة/وصف |
| `website` | VARCHAR(255) NULL | الموقع الرسمي |
| `is_active` | BOOLEAN default 1 | INDEX — تفعيل/إخفاء |
| `sort_order` | INT default 0 | ترتيب العرض |
| `timestamps` | | |
| `deleted_at` | TIMESTAMP NULL | SoftDeletes — لتفادي كسر FK للكتب |

**الفهارس:** `UNIQUE(slug)` · `INDEX(is_active, sort_order)` · `INDEX(name)` · `INDEX(name_normalized)`.

> **قرار الاتساق (بلا تكرار):** لا تُكرَّر جداول `media` و`seo_meta` داخل `publishers`.
> - **الشعار** عبر Spatie Media Library (collection = `logo`) — بلا عمود `logo_media_id`.
> - **الـ SEO** عبر علاقة polymorphic مع `seo_meta` (`seoable_type = Publisher`) — بلا عمود `seo_meta` JSON.

> **بيانات الناشرين الأولية (Seeder):** ناشر افتراضي واحد باسم **«قصص أطفال»** يُربط به كل كتاب بلا دار ظاهرة، بالإضافة إلى دور النشر الحقيقية التي ظهرت على الأغلفة: **سِجرة، دار الشروق، بيت الحكمة (سوريا)، زغلول، دار النون، رؤية للنشر، MOON، 80Fekra**.

### `products` — الكتب (23 كتابًا في الكتالوج الفعلي)

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `category_id` | BIGINT UNSIGNED | FK → `categories.id` (restrict) — القسم الرئيسي |
| `publisher_id` | BIGINT UNSIGNED NULL | **FK → `publishers.id` (set null)** — دار النشر |
| `title` | VARCHAR(200) | عنوان الكتاب |
| `slug` | VARCHAR(220) | **UNIQUE** |
| `sku` | VARCHAR(60) NULL | UNIQUE nullable — كود داخلي |
| `short_description` | VARCHAR(500) NULL | |
| `long_description` | LONGTEXT NULL | HTML من التحرير |
| `price` | DECIMAL(10,2) **NULL** | السعر الحالي (نطاق 150–450 ج.م). *NULL مسموح مؤقتًا* |
| `compare_at_price` | DECIMAL(10,2) NULL | السعر قبل الخصم (المشطوب — للعروض) |
| `cost_price` | DECIMAL(10,2) NULL | تكلفة داخلية (تُخفى عن الدعم) |
| `stock_quantity` | INT default 0 | |
| `stock_status` | ENUM('in_stock','out_of_stock','preorder') default 'in_stock' | INDEX |
| `manage_stock` | BOOLEAN default 1 | إن مفعّل يُنقص المخزون تلقائيًا |
| `author` | VARCHAR(150) NULL | المؤلف |
| `illustrator` | VARCHAR(150) NULL | الرسّام |
| `age_min` | TINYINT UNSIGNED NULL | أصغر عمر |
| `age_max` | TINYINT UNSIGNED NULL | أكبر عمر |
| `age_label` | VARCHAR(50) NULL | نص العرض «4 - 9 سنوات» |
| `pages_count` | SMALLINT UNSIGNED NULL | عدد الصفحات |
| `isbn` | VARCHAR(20) NULL | اختياري |
| `weight_grams` | SMALLINT UNSIGNED NULL | لحساب الشحن |
| `learning_outcomes` | JSON NULL | مصفوفة مخرجات التعلّم |
| `is_published` | BOOLEAN default 0 | INDEX — حالة النشر |
| `is_featured` | BOOLEAN default 0 | INDEX — مميّز |
| `published_at` | TIMESTAMP NULL | |
| `sort_order` | INT default 0 | INDEX |
| `views_count` | INT UNSIGNED default 0 | |
| `avg_rating` | DECIMAL(2,1) default 0 | مخزّن للأداء (denormalized) |
| `reviews_count` | INT UNSIGNED default 0 | denormalized |
| `timestamps` | | |
| `deleted_at` | TIMESTAMP NULL | SoftDeletes |

**الفهارس:**
- `UNIQUE(slug)` · `UNIQUE(sku)`.
- `INDEX(category_id)` · `INDEX(publisher_id)`.
- فهرس مركّب للعرض العام: `INDEX(is_published, is_featured, sort_order)` — يخدم صفحات القوائم في فهرس واحد.
- `INDEX(stock_status)` · `INDEX(published_at)`.
- `FULLTEXT(title, short_description)` للبحث — بديل عن `LIKE '%..%'` الثقيل (انظر المجموعة 3).

> **صندوق ملاحظة — بيانات ناقصة يعالجها الأدمن:** الكتاب **«أنا لستُ شقيًا!» (BOOK1) بلا سعر** ⇒ `price` يقبل NULL مؤقتًا ويُمنع نشره حتى إضافة السعر. الكتاب **«هون عليك» (BOOK10) بلا صورة غلاف على القرص** ⇒ على الأدمن رفع الغلاف عبر Media Library.

> **ملاحظة نطاق المنتج:** المتجر يبيع كتبًا **منسّقة/مطبوعة جاهزة** — **لا** يوجد تخصيص باسم/صورة الطفل. لذلك لا يحتوي `products` أو `order_items` على أي حقول تخصيص شخصية.

### `product_category` — ربط متعدد بأقسام إضافية (اختياري)

| العمود | النوع |
|---|---|
| `product_id` | BIGINT UNSIGNED (FK) |
| `category_id` | BIGINT UNSIGNED (FK) |

PK مركّب `(product_id, category_id)` + `INDEX(category_id)`. القسم الرئيسي يظل في `products.category_id`.

### `media` — الوسائط (Spatie Media Library)

يُنشئها `spatie/laravel-medialibrary` (polymorphic): `id, model_type, model_id, collection_name ('cover'/'gallery'/'logo'), name, file_name, mime_type, disk, size, manipulations JSON, custom_properties JSON, generated_conversions JSON, order_column, timestamps`.
**الفهارس:** `INDEX(model_type, model_id)` · `INDEX(collection_name)`.
**التحويلات:** توليد WebP بأحجام متعددة (thumb/medium/large) — مهم لأداء الصور على إنترنت ضعيف.

> **علاقات polymorphic للوسائط:** `products` (cover/gallery)، `publishers` (logo)، `reviews` (إثبات اجتماعي).

---

## المجموعة 3: فهرس البحث المطبّع (Normalized Search Index) 🆕

يخدم **محرك البحث والفلترة الاحترافي**: بحث باسم الكتاب/الدار/المؤلف مع **تطبيع عربي** (توحيد الهمزات، الألف، التاء المربوطة/الهاء، الياء/الألف المقصورة، حذف التشكيل والتطويل) + اقتراح فوري.

### `search_index` — جدول بحث مسطّح مُطبّع

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `product_id` | BIGINT UNSIGNED | FK → `products.id` (cascade) · **UNIQUE** (صف لكل منتج) |
| `title_normalized` | VARCHAR(255) | عنوان الكتاب بعد التطبيع العربي |
| `author_normalized` | VARCHAR(190) NULL | اسم المؤلف مطبّعًا |
| `publisher_normalized` | VARCHAR(190) NULL | اسم الناشر مطبّعًا (مُشتق من `publishers.name_normalized`) |
| `category_normalized` | VARCHAR(150) NULL | اسم القسم مطبّعًا |
| `keywords` | TEXT NULL | كلمات مفتاحية إضافية (وسوم، مرادفات) مطبّعة |
| `search_blob` | TEXT | الحقل الموحّد المفهرس FULLTEXT (title + author + publisher + category + keywords) |
| `is_published` | BOOLEAN default 0 | INDEX — لاستبعاد غير المنشور من النتائج |
| `updated_at` | TIMESTAMP NULL | |

**الفهارس:**
- `UNIQUE(product_id)`.
- `FULLTEXT(search_blob)` — لبحث ذي صلة (relevance) وسريع (`MATCH ... AGAINST` في وضع `IN BOOLEAN MODE`).
- `INDEX(title_normalized)` — لدعم الاقتراح الفوري بـ `title_normalized LIKE 'term%'` (بادئة تستخدم الفهرس).
- `INDEX(is_published)`.

> **آلية التزامن (Sync):** يُملأ `search_index` عبر Observer على `Product` (و`Publisher`) عند الإنشاء/التعديل/الحذف، بحيث تبقى الحقول المطبّعة متسقة. عند تعذّر ذلك آنيًا، Job مجدول (queue: database) يعيد بناء الصفوف المتغيّرة.

**بديل مبسّط (لو رُفض جدول منفصل):** الاكتفاء بـ `FULLTEXT(title, short_description)` على `products` + عمود `title_normalized` مضاف مباشرةً إلى `products`. الجدول المنفصل مُفضَّل لعزل حمل البحث عن جدول الكتابة (`products`) وإبقاء `search_blob` الضخم خارج استعلامات القوائم.

**مقتطف الفلترة حسب الناشر (تكامل البحث):**
```php
// فلتر ?publisher=slug
$query->when($request->publisher, fn ($q, $slug) =>
    $q->whereHas('publisher', fn ($p) => $p->where('slug', $slug)));

// بحث موحّد عبر search_index (FULLTEXT)
Product::whereIn('id', function ($sub) use ($normalizedTerm) {
    $sub->select('product_id')->from('search_index')
        ->whereRaw("MATCH(search_blob) AGAINST(? IN BOOLEAN MODE)", [$normalizedTerm.'*'])
        ->where('is_published', 1);
});
```

---

## المجموعة 4: الطلبات والدفع (Orders & Payments)

### `orders`

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `order_number` | VARCHAR(20) | **UNIQUE** — رقم عرض (مثل `QSQ-2026-000123`) |
| `user_id` | BIGINT UNSIGNED NULL | FK → `users.id` — ضيف مسموح (guest checkout) |
| `status` | ENUM('pending','confirmed','processing','shipped','delivered','completed','cancelled','refused','refunded') default 'pending' | INDEX |
| `customer_name` | VARCHAR(150) | |
| `customer_phone` | VARCHAR(20) | INDEX — محور بيع واتساب |
| `customer_phone_alt` | VARCHAR(20) NULL | |
| `customer_email` | VARCHAR(191) NULL | |
| `governorate` | VARCHAR(50) | INDEX — المحافظة |
| `city` | VARCHAR(80) NULL | |
| `address_line` | VARCHAR(300) | عنوان الشحن |
| `address_notes` | VARCHAR(300) NULL | علامة مميزة |
| `subtotal` | DECIMAL(10,2) | مجموع البنود قبل الخصم/الشحن |
| `discount_total` | DECIMAL(10,2) default 0 | |
| `shipping_total` | DECIMAL(10,2) default 0 | |
| `grand_total` | DECIMAL(10,2) | النهائي |
| `coupon_id` | BIGINT UNSIGNED NULL | FK → `coupons.id` (set null) |
| `coupon_code` | VARCHAR(50) NULL | مخزّن نصيًا وقت الطلب (snapshot) |
| `payment_method` | ENUM('cod','instapay','vodafone_cash','bank_transfer','online_gateway') | INDEX |
| `payment_status` | ENUM('unpaid','pending_review','partially_paid','paid','refunded','failed') default 'unpaid' | INDEX |
| `shipping_company` | VARCHAR(50) NULL | بوسطة/ميلسي/أرامكس |
| `tracking_number` | VARCHAR(80) NULL | |
| `whatsapp_confirmed_at` | TIMESTAMP NULL | تأكيد الطلب عبر واتساب |
| `confirmed_by` | BIGINT UNSIGNED NULL | FK → `users.id` (الأدمن) |
| `customer_note` | TEXT NULL | |
| `admin_note` | TEXT NULL | |
| `ip_address` | VARCHAR(45) NULL | |
| `timestamps` | | |
| `deleted_at` | TIMESTAMP NULL | SoftDeletes |

**الفهارس:** `UNIQUE(order_number)` · `INDEX(user_id)` · `INDEX(status)` · `INDEX(payment_status)` · `INDEX(payment_method)` · `INDEX(customer_phone)` · `INDEX(governorate)` · `INDEX(created_at)`.

### `order_items`

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `order_id` | BIGINT UNSIGNED | FK → `orders.id` (cascade) |
| `product_id` | BIGINT UNSIGNED NULL | FK → `products.id` (set null — يبقى البند لو حُذف المنتج) |
| `product_title` | VARCHAR(200) | لقطة snapshot وقت الطلب |
| `unit_price` | DECIMAL(10,2) | |
| `quantity` | SMALLINT UNSIGNED default 1 | |
| `line_total` | DECIMAL(10,2) | `unit_price × quantity` |
| `timestamps` | | |

**الفهارس:** `INDEX(order_id)` · `INDEX(product_id)`.

### `order_status_history` — تتبّع تغيّر الحالات

| id PK | order_id FK | from_status VARCHAR(30) NULL | to_status VARCHAR(30) | note VARCHAR(300) NULL | changed_by BIGINT UNSIGNED NULL (FK users) | created_at |

**الفهرس:** `INDEX(order_id)`.

### `payment_methods` — إعدادات تفعيل/تعطيل كل طريقة

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `code` | VARCHAR(40) | **UNIQUE** — cod/instapay/vodafone_cash/bank_transfer/online_gateway |
| `name` | VARCHAR(80) | اسم العرض |
| `type` | ENUM('cash_on_delivery','manual_transfer','online_gateway') | |
| `is_enabled` | BOOLEAN default 1 | INDEX |
| `instructions` | TEXT NULL | تعليمات التحويل للعميل |
| `account_details` | JSON NULL | رقم المحفظة/الحساب/الـ IBAN |
| `gateway_provider` | VARCHAR(40) NULL | paymob/kashier |
| `config` | JSON NULL | إعدادات غير حساسة (المفاتيح السرية في `.env` أو settings المشفّرة) |
| `requires_proof` | BOOLEAN default 0 | هل يتطلب رفع إثبات |
| `sort_order` | INT default 0 | |
| `timestamps` | | |

**الفهارس:** `UNIQUE(code)` · `INDEX(is_enabled)`.

> **صندوق ملاحظة — التدهور اللطيف (Graceful Degradation):** إن كان `online_gateway` معطّلًا أو بلا مفتاح، يظهر المسار اليدوي (إنستاباي/فودافون كاش/تحويل + رفع إثبات) و COD تلقائيًا.

### `payments` — سجل عمليات الدفع الفعلية

| id PK | order_id FK (cascade) | payment_method_code VARCHAR(40) | amount DECIMAL(10,2) | status ENUM('pending','pending_review','completed','failed','refunded') | transaction_ref VARCHAR(120) NULL | gateway_response JSON NULL | paid_at TIMESTAMP NULL | timestamps |

**الفهارس:** `INDEX(order_id)` · `INDEX(status)` · `INDEX(transaction_ref)`.

### `payment_proofs` — رفع إثبات التحويل اليدوي

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `order_id` | BIGINT UNSIGNED | FK → `orders.id` (cascade) |
| `payment_id` | BIGINT UNSIGNED NULL | FK → `payments.id` |
| `method_code` | VARCHAR(40) | instapay/vodafone_cash/bank_transfer |
| `file_path` | VARCHAR(255) | صورة الإثبات (أو عبر media library) |
| `amount` | DECIMAL(10,2) NULL | المبلغ المُحوّل |
| `sender_reference` | VARCHAR(120) NULL | رقم العملية/المحوِّل |
| `review_status` | ENUM('pending_review','approved','rejected') default 'pending_review' | INDEX |
| `reviewed_by` | BIGINT UNSIGNED NULL | FK → `users.id` |
| `reviewed_at` | TIMESTAMP NULL | |
| `review_note` | VARCHAR(300) NULL | سبب الرفض |
| `timestamps` | | |

**الفهارس:** `INDEX(order_id)` · `INDEX(review_status)`.

---

## المجموعة 5: الكوبونات (Coupons)

### `coupons`

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `code` | VARCHAR(50) | **UNIQUE** (مثل `STORY10`) |
| `description` | VARCHAR(200) NULL | |
| `type` | ENUM('percentage','fixed') | نسبة أو مبلغ |
| `value` | DECIMAL(10,2) | القيمة (10 = 10% أو 10ج) |
| `min_order_total` | DECIMAL(10,2) NULL | حد أدنى للطلب |
| `max_discount` | DECIMAL(10,2) NULL | سقف الخصم للنسبة |
| `starts_at` | TIMESTAMP NULL | |
| `expires_at` | TIMESTAMP NULL | INDEX |
| `usage_limit` | INT UNSIGNED NULL | حد الاستخدام الكلي |
| `usage_limit_per_user` | INT UNSIGNED NULL | لكل مستخدم |
| `used_count` | INT UNSIGNED default 0 | عدّاد denormalized |
| `applies_to` | ENUM('all','categories','products') default 'all' | نطاق التطبيق |
| `is_active` | BOOLEAN default 1 | INDEX |
| `free_shipping` | BOOLEAN default 0 | شحن مجاني |
| `timestamps` | | |

**الفهارس:** `UNIQUE(code)` · `INDEX(is_active)` · `INDEX(expires_at)`.

- `coupon_product` (product_id, coupon_id) — PK مركّب + `INDEX(coupon_id)`.
- `coupon_category` (category_id, coupon_id) — PK مركّب + `INDEX(coupon_id)`.
- `coupon_usages`: id PK | coupon_id FK (cascade) | order_id FK NULL | user_id FK NULL | discount_amount DECIMAL(10,2) | created_at. **الفهارس:** `INDEX(coupon_id)` · `INDEX(user_id)` — لفرض `usage_limit_per_user`.

---

## المجموعة 6: إدارة المحتوى (CMS)

> **تحكم كامل:** نصوص الرئيسية، الروابط/القوائم، صفحات جديدة، منتجات، كوبونات، بوب أب، SEO لكل صفحة.

### `pages` — صفحات ديناميكية

| id PK | title VARCHAR(200) | slug VARCHAR(220) **UNIQUE** | content LONGTEXT NULL | template VARCHAR(60) NULL | is_published BOOLEAN default 0 (INDEX) | published_at TIMESTAMP NULL | sort_order INT default 0 | timestamps | deleted_at |

### `content_blocks` — بلوكات الصفحة الرئيسية القابلة للتحرير

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `key` | VARCHAR(80) | **UNIQUE** — معرّف البلوك (hero_slider, featured_row…) |
| `area` | VARCHAR(60) | المنطقة (homepage/footer…) |
| `type` | ENUM('text','html','banner','slider','products_grid','cta','image') | |
| `title` | VARCHAR(200) NULL | |
| `content` | JSON NULL | بيانات مرنة حسب النوع |
| `is_active` | BOOLEAN default 1 | |
| `sort_order` | INT default 0 | |
| `timestamps` | | |

**الفهارس:** `UNIQUE(key)` · `INDEX(area, is_active, sort_order)`.

### `banners` — البانرات والسلايدر

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `block_id` | BIGINT UNSIGNED NULL | FK → `content_blocks.id` (cascade) |
| `title` | VARCHAR(200) NULL | |
| `subtitle` | VARCHAR(300) NULL | |
| `image_path` | VARCHAR(255) | (webp) |
| `image_mobile_path` | VARCHAR(255) NULL | نسخة موبايل (mobile-first) |
| `link_url` | VARCHAR(300) NULL | |
| `link_label` | VARCHAR(80) NULL | |
| `product_id` | BIGINT UNSIGNED NULL | FK → `products.id` |
| `position` | VARCHAR(40) default 'home_slider' | INDEX |
| `starts_at` / `ends_at` | TIMESTAMP NULL | جدولة العرض |
| `is_active` | BOOLEAN default 1 | |
| `sort_order` | INT default 0 | |
| `timestamps` | | |

**الفهارس:** `INDEX(position, is_active, sort_order)` · `INDEX(product_id)`.

### `menus`

| id PK | name VARCHAR(80) | location VARCHAR(50) **UNIQUE** (header/footer/mobile) | is_active BOOLEAN default 1 | timestamps |

### `menu_items`

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `menu_id` | BIGINT UNSIGNED | FK → `menus.id` (cascade) |
| `parent_id` | BIGINT UNSIGNED NULL | FK self (قوائم متعددة المستويات) |
| `label` | VARCHAR(100) | |
| `url` | VARCHAR(300) NULL | رابط مباشر |
| `link_type` | ENUM('url','page','category','product','publisher') default 'url' | يدعم الربط بصفحة ناشر |
| `linkable_type` | VARCHAR(150) NULL | polymorphic |
| `linkable_id` | BIGINT UNSIGNED NULL | |
| `target` | ENUM('_self','_blank') default '_self' | |
| `icon` | VARCHAR(60) NULL | |
| `sort_order` | INT default 0 | |
| `is_active` | BOOLEAN default 1 | |
| `timestamps` | | |

**الفهارس:** `INDEX(menu_id, parent_id, sort_order)` · `INDEX(linkable_type, linkable_id)`.

---

## المجموعة 7: البوب أب والاستبيانات (Popups & Surveys)

### `popups`

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `title` | VARCHAR(200) | |
| `type` | ENUM('promo','survey','newsletter','announcement') | INDEX |
| `content` | LONGTEXT NULL | HTML للدعاية |
| `image_path` | VARCHAR(255) NULL | |
| `survey_id` | BIGINT UNSIGNED NULL | FK → `surveys.id` |
| `cta_label` | VARCHAR(80) NULL | |
| `cta_url` | VARCHAR(300) NULL | |
| `display_trigger` | ENUM('on_load','on_exit','on_scroll','after_delay') default 'on_load' | |
| `delay_seconds` | SMALLINT UNSIGNED NULL | |
| `display_frequency` | ENUM('once','once_per_session','always') default 'once_per_session' | |
| `target_pages` | JSON NULL | استهداف مسارات محددة |
| `target_devices` | JSON NULL | mobile/desktop |
| `starts_at` / `ends_at` | TIMESTAMP NULL | |
| `is_active` | BOOLEAN default 1 | |
| `priority` | INT default 0 | ترتيب عند التعدد |
| `timestamps` | | |

**الفهارس:** `INDEX(type)` · `INDEX(is_active)` · `INDEX(starts_at, ends_at)`.

### `surveys`

| id PK | title VARCHAR(200) | description TEXT NULL | slug VARCHAR(220) **UNIQUE** | is_active BOOLEAN default 1 | starts_at/ends_at TIMESTAMP NULL | timestamps |

### `survey_questions`

| id PK | survey_id FK (cascade) | question_text VARCHAR(500) | type ENUM('text','textarea','single_choice','multi_choice','rating','yes_no') | options JSON NULL | is_required BOOLEAN default 0 | sort_order INT default 0 | timestamps | — **الفهرس:** `INDEX(survey_id, sort_order)`.

### `survey_responses`

| id PK | survey_id FK (cascade) | user_id FK NULL | respondent_name VARCHAR(150) NULL | respondent_phone VARCHAR(20) NULL | ip_address VARCHAR(45) NULL | submitted_at TIMESTAMP | timestamps | — **الفهارس:** `INDEX(survey_id)` · `INDEX(user_id)`.

### `survey_answers`

| id PK | survey_response_id FK (cascade) | survey_question_id FK (cascade) | answer_text TEXT NULL | answer_options JSON NULL | rating_value TINYINT NULL | timestamps | — **الفهارس:** `INDEX(survey_response_id)` · `INDEX(survey_question_id)`.

---

## المجموعة 8: الـ SEO (Polymorphic)

### `seo_meta`

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `seoable_type` | VARCHAR(150) | polymorphic (Product/Category/Page/**Publisher**) |
| `seoable_id` | BIGINT UNSIGNED | |
| `meta_title` | VARCHAR(255) NULL | |
| `meta_description` | VARCHAR(320) NULL | |
| `meta_keywords` | VARCHAR(255) NULL | |
| `canonical_url` | VARCHAR(300) NULL | |
| `og_title` | VARCHAR(255) NULL | |
| `og_description` | VARCHAR(320) NULL | |
| `og_image_path` | VARCHAR(255) NULL | |
| `robots` | ENUM('index,follow','noindex,follow','index,nofollow','noindex,nofollow') default 'index,follow' | |
| `structured_data` | JSON NULL | JSON-LD مخصص |
| `timestamps` | | |

**الفهرس:** `UNIQUE(seoable_type, seoable_id)` — سطر SEO واحد لكل كيان.

> **تكامل الناشر:** صفحة `/publishers/{slug}` تُخرج `Organization` (+ `ItemList` بكتبه)، وصفحة الكتاب تُخرج `Book` مع كتلة `publisher`. كلاهما يقرأ من `seo_meta` عبر `seoable_type = Publisher/Product` — **بلا جدول جديد**.

---

## المجموعة 9: المراجعات والاستفسارات (Reviews & Inquiries)

### `reviews` — مراجعات المنتجات + ردود الدعم

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `product_id` | BIGINT UNSIGNED | FK → `products.id` (cascade) |
| `user_id` | BIGINT UNSIGNED NULL | FK → `users.id` |
| `parent_id` | BIGINT UNSIGNED NULL | FK self — رد الدعم على المراجعة |
| `author_name` | VARCHAR(150) | |
| `author_phone` | VARCHAR(20) NULL | |
| `rating` | TINYINT UNSIGNED NULL | 1-5 (فارغ للردود) |
| `title` | VARCHAR(200) NULL | |
| `body` | TEXT | |
| `has_media` | BOOLEAN default 0 | صور/فيديو (إثبات اجتماعي) |
| `status` | ENUM('pending','published','hidden','spam') default 'pending' | INDEX |
| `is_verified_purchase` | BOOLEAN default 0 | |
| `replied_by` | BIGINT UNSIGNED NULL | FK → `users.id` (الدعم) |
| `timestamps` | | |

**الفهارس:** `INDEX(product_id, status)` · `INDEX(user_id)` · `INDEX(parent_id)` · `INDEX(status)`. الوسائط عبر media library (polymorphic → Review).

### `inquiries` — الاستفسارات / نموذج التواصل (يراها الدعم)

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `type` | ENUM('contact','product_question','complaint','wholesale_b2b') default 'contact' | INDEX |
| `product_id` | BIGINT UNSIGNED NULL | FK → `products.id` |
| `name` | VARCHAR(150) | |
| `phone` | VARCHAR(20) | INDEX |
| `email` | VARCHAR(191) NULL | |
| `subject` | VARCHAR(200) NULL | |
| `message` | TEXT | |
| `status` | ENUM('new','in_progress','answered','closed') default 'new' | INDEX |
| `assigned_to` | BIGINT UNSIGNED NULL | FK → `users.id` (الدعم) |
| `admin_reply` | TEXT NULL | |
| `replied_at` | TIMESTAMP NULL | |
| `ip_address` | VARCHAR(45) NULL | |
| `timestamps` | | |

**الفهارس:** `INDEX(type)` · `INDEX(status)` · `INDEX(assigned_to)` · `INDEX(phone)` · `INDEX(product_id)`.

### `faqs` — الأسئلة الشائعة

| id PK | category_id BIGINT UNSIGNED NULL (FK categories) | question VARCHAR(300) | answer TEXT | sort_order INT default 0 | is_active BOOLEAN default 1 | timestamps | — **الفهرس:** `INDEX(is_active, sort_order)`.

---

## المجموعة 10: الإعدادات والشحن والتدقيق والبنية التحتية

### `settings` — key/value عام

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `group` | VARCHAR(50) default 'general' | general/contact/payment/shipping/social |
| `key` | VARCHAR(100) | **UNIQUE** (whatsapp_number, cod_enabled…) |
| `value` | LONGTEXT NULL | |
| `type` | ENUM('string','text','boolean','integer','json','encrypted') default 'string' | |
| `is_encrypted` | BOOLEAN default 0 | مفاتيح الدفع الحساسة تُشفّر (Laravel Crypt) |
| `autoload` | BOOLEAN default 1 | تحميل ضمن كاش الإعدادات |
| `timestamps` | | |

**الفهارس:** `UNIQUE(key)` · `INDEX(group)` · `INDEX(autoload)`.

> **صندوق ملاحظة — أمان المفاتيح:** مفاتيح Paymob/Kashier في `.env` أساسًا؛ إن خُزّنت هنا فبـ `type=encrypted` عبر Crypt. **لا تُخزَّن أبدًا كنص صريح.**

### `governorates` + `shipping_rates` — الشحن حسب المحافظة

- `governorates`: id PK | name_ar VARCHAR(50) | name_en VARCHAR(50) NULL | is_active BOOLEAN default 1 | sort_order INT.
- `shipping_rates`: id PK | governorate_id FK (cascade) | shipping_company VARCHAR(50) | rate DECIMAL(10,2) | free_over DECIMAL(10,2) NULL | est_days_min/max TINYINT | cod_available BOOLEAN default 1 | is_active BOOLEAN default 1 | timestamps. **الفهرس:** `INDEX(governorate_id)`.

### `activity_log` — سجل التدقيق (spatie/laravel-activitylog)

يُنشئها الحزمة: `id, log_name, description, subject_type, subject_id, causer_type, causer_id, properties JSON, event, batch_uuid, timestamps`.
**الفهارس:** `INDEX(log_name)` · `INDEX(subject_type, subject_id)` · `INDEX(causer_type, causer_id)`. يسجّل أفعال الأدمن (إنشاء/تعديل منتج، تغيير حالة طلب، مراجعة إثبات دفع…).

### جداول البنية التحتية (database driver — لا Redis على المشترك)

- `sessions` (id VARCHAR PK, user_id NULL, ip_address, user_agent, payload LONGTEXT, last_activity INT INDEX) — `SESSION_DRIVER=database`.
- `cache` + `cache_locks` — `CACHE_STORE=database`.
- `jobs` + `job_batches` + `failed_jobs` — `QUEUE_CONNECTION=database`.
- `password_reset_tokens`, `personal_access_tokens` (لو Sanctum).

> على استضافة Hostinger المشتركة يُعتمد driver قاعدة البيانات بدلًا من Redis (غير متاح).

---

## ملاحظات فهرسة وأداء MySQL على استضافة مشتركة

1. **`utf8mb4` وحدود المفاتيح:** كل عمود VARCHAR مفهرس (`email`, `slug`) ≤ 191 محرفًا، أو الاعتماد على `ROW_FORMAT=DYNAMIC` (افتراضي في MySQL 8).
2. **Denormalization محسوبة:** `avg_rating`, `reviews_count`, `used_count`, `views_count` مخزّنة لتفادي `COUNT()`/`AVG()` المتكرر على كل عرض صفحة. تُحدَّث عبر Observers/Events.
3. **الفهارس المركّبة تتبع ترتيب الاستعلام:** `products(is_published, is_featured, sort_order)` يخدم صفحة العرض في فهرس واحد. لا تكرّر فهارس مفردة على نفس الأعمدة.
4. **قلّل الفهارس على الجداول كثيرة الكتابة** (`orders`, `order_items`): كل فهرس تكلفة على `INSERT`. اكتفِ بالمُستعلَم فعلًا (status, phone, created_at).
5. **JSON بدل جداول EAV:** `learning_outcomes`, `account_details`, `config`, `target_pages` كـ JSON يقلّل الجداول والـ JOINs. لا تفهرس داخل JSON على المشترك (generated columns قد لا تُدعم).
6. **FULLTEXT للبحث** بدل `LIKE '%x%'` (الأخير لا يستخدم فهرسًا). فعّله على `search_index(search_blob)` و`products(title, short_description)`. للاقتراح الفوري استخدم بادئة `LIKE 'term%'` على `title_normalized` (تستخدم الفهرس).
7. **تطبيع البحث العربي يتم في PHP** (طبقة الخدمة/Observer) وتُخزَّن النتيجة في أعمدة `*_normalized`؛ لا تعتمد على دوال MySQL لتطبيع العربية.
8. **FK بـ `ON DELETE` مضبوطة:** cascade للأبناء الحقيقيين (`order_items`, `survey_answers`)، set null للمراجع الاختيارية (`products.publisher_id`, `order_items.product_id`) لحفظ السجل التاريخي.
9. **Pagination دائمًا** — استخدم `simplePaginate` حيث أمكن (أخف على `COUNT`).
10. **الأرشفة:** `activity_log` و`sessions` تنمو بسرعة — أضِف Cron تنظيف دوري لحذف السجلات الأقدم من N يوم.
11. **حجم الصفوف:** أبقِ الأعمدة الضخمة (`long_description`, `content`, `search_blob`) في جداول تُستعلم بمفردها؛ تجنّب `SELECT` لها في القوائم.

---

## الترتيب المقترح للـ Migrations

```text
# 1. أساس Laravel والبنية التحتية
0001_create_users_table
0002_create_password_reset_tokens_table
0003_create_sessions_table
0004_create_cache_table
0005_create_jobs_table              # jobs + job_batches + failed_jobs
0006_create_personal_access_tokens_table

# 2. الصلاحيات (Spatie) — قبل ربط أي شيء بالأدوار
0010_create_permission_tables

# 3. التصنيف والناشرون والمنتجات
0020_create_categories_table        # (parent_id self FK)
0021_create_publishers_table        # كيان دار النشر
0022_create_products_table          # يحوي publisher_id FK → publishers
0023_create_product_category_table
0024_create_media_table             # Spatie Media Library
0025_add_category_fk_to_users       # users.category_id (نطاق الدعم)
0026_create_support_user_products_table

# 4. فهرس البحث المطبّع
0030_create_search_index_table      # + FULLTEXT(search_blob)

# 5. الجغرافيا والشحن
0040_create_governorates_table
0041_create_shipping_rates_table

# 6. الكوبونات
0050_create_coupons_table
0051_create_coupon_product_table
0052_create_coupon_category_table
0053_create_coupon_usages_table

# 7. الطلبات والدفع
0060_create_payment_methods_table
0061_create_orders_table            # coupon_id FK → coupons موجود
0062_create_order_items_table
0063_create_order_status_history_table
0064_create_payments_table
0065_create_payment_proofs_table
0066_add_order_fk_to_coupon_usages  # حل الاعتماد الدائري coupons↔orders

# 8. الـ CMS
0070_create_pages_table
0071_create_content_blocks_table
0072_create_banners_table
0073_create_menus_table
0074_create_menu_items_table

# 9. البوب أب والاستبيانات
0080_create_surveys_table
0081_create_survey_questions_table
0082_create_popups_table            # survey_id FK → surveys
0083_create_survey_responses_table
0084_create_survey_answers_table

# 10. المراجعات والاستفسارات
0090_create_reviews_table
0091_create_inquiries_table
0092_create_faqs_table

# 11. SEO والإعدادات والتدقيق
0100_create_seo_meta_table
0101_create_settings_table
0102_create_activity_log_table
```

> **قاعدة الترتيب:** كل جدول مرجعي (parent) قبل من يشير إليه. `publishers` قبل `products` (لوجود `publisher_id`). الاعتماد الدائري (`coupons ↔ orders` عبر `coupon_usages`) يُحلّ بإضافة الـ FK في migration لاحق (0066).

---

## مثال Migration — جدول الناشرين (مرجعي)

```php
Schema::create('publishers', function (Blueprint $table) {
    $table->id();
    $table->string('name', 190);
    $table->string('slug', 190)->unique();
    $table->string('name_normalized', 190)->nullable();
    $table->text('description')->nullable();
    $table->string('website', 255)->nullable();
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['is_active', 'sort_order']);
    $table->index('name');
    $table->index('name_normalized');
});
```

```php
// داخل create_products_table:
$table->foreignId('publisher_id')
      ->nullable()
      ->after('category_id')
      ->constrained('publishers')   // ينشئ الفهرس تلقائيًا
      ->nullOnDelete()              // ON DELETE SET NULL
      ->cascadeOnUpdate();
```

> **تنبيه تكرار فهرس:** `constrained()` ينشئ فهرسًا تلقائيًا على `publisher_id`؛ **لا** تُضِف `$table->index('publisher_id')` صراحةً معه لتفادي فهرس مكرر.

**علاقة النموذج (withDefault لعرض الافتراضي عند NULL):**
```php
// Product
public function publisher() {
    return $this->belongsTo(Publisher::class)
                ->withDefault(['name' => 'قصص أطفال']);
}
// Publisher
public function activeBooks() {
    return $this->hasMany(Product::class)->where('is_published', 1);
}
```

---

## ملخّص العلاقات الأساسية (Relationships)

- `categories` **1—N** `products` (رئيسي) + **N—N** عبر `product_category`. `categories` self-referencing (`parent_id`).
- `publishers` **1—N** `products` (عبر `products.publisher_id`, set null). كتب بلا دار ظاهرة تُربط بالناشر الافتراضي «قصص أطفال».
- `products` **1—1** `search_index` (مطبّع) + **1—N** `media`, `reviews`, `order_items`, `banners`, `inquiries`.
- `users` **N—N** `roles`/`permissions` (Spatie) · **N—1** `categories` (نطاق الدعم) · **1—N** `support_user_products`.
- `orders` **1—N** `order_items`, `payments`, `payment_proofs`, `order_status_history` · **N—1** `users`, `coupons`.
- `coupons` **N—N** `products`/`categories` · **1—N** `coupon_usages`.
- `surveys` **1—N** `survey_questions`/`survey_responses`; `survey_responses` **1—N** `survey_answers`; `popups` **N—1** `surveys`.
- `seo_meta` **polymorphic** → `products` / `categories` / `pages` / `publishers`.
- `menus` **1—N** `menu_items` (self-referencing + polymorphic linkable يشمل publisher).
- `activity_log` **polymorphic** (subject + causer).

> هذا المخطط يغطي كامل متطلبات المشروع (CMS كامل، أدوار متعددة، دفع مصري بمسارات متعددة، شحن بالمحافظة، كيان دار النشر، محرك بحث وفلترة مطبّع)، ومتوافق مع Filament v3 + حزم Spatie (Permission / Media Library / Activity Log) وبنية database-driver الموصى بها لاستضافة Hostinger المشتركة دون Redis.

</div>
