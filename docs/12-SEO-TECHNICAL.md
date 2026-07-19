# 12 — SEO التقني: ما نُصدره ولماذا

> يوثّق هذا الملف طبقة SEO التقنية بعد تدقيق 2026-07-19. الغرض منه أن يعرف من يعدّل
> القوالب **أين تُصدر كل إشارة ولماذا**، فلا يُعاد إدخال ازدواج أزلناه.

---

## 0. الخلاصة أولًا: ما لا يفعله الكود

**الروابط الفرعية (Sitelinks) لا تُطلَب بوسم.** توثيق Google: *"At the moment, sitelinks
are automated"*. لا يوجد نوع schema يُنتجها، وأداة الخفض القديمة في Search Console
أُزيلت بلا بديل. ما يُنتجها: عمر النطاق + السلطة + **حجم البحث على اسم العلامة**.

كذلك **«اسم الموقع»** في النتائج (عرض «قصاقيص أطفال» بدل `qasaqis.store`) يتأثّر بالوسوم
لكنه لا يُستعجل بها — Google يحتاج زحفًا ومعالجة وثقة.

**الشرط المسبق الذي لا يُغني عنه أي كود: تفعيل Search Console وإرسال الـ sitemap.**

---

## 1. أين تُصدر كل إشارة

كل الإشارات مركزها `resources/views/layouts/app.blade.php`. **القاعدة: الصفحة تغلب
قيمة التخطيط بـ `@section`، ولا تدفع وسمًا ثانيًا بـ `@push`.**

| الإشارة | منفذ الصفحة | افتراض التخطيط |
|---|---|---|
| `<title>` / `og:title` / `twitter:title` | `@section('title', …)` | العلامة + الشعار |
| `meta description` / `og:description` | `@section('meta_description', …)` | `common.tagline` |
| `og:title` منفصلًا عن `<title>` | `@section('og_title', …)` | يرث `title` |
| `og:description` منفصلًا | `@section('og_description', …)` | يرث `meta_description` |
| `robots` | `@section('seo_robots', …)` | `index, follow` |
| `canonical` | `@section('seo_canonical', …)` | `url()->current()` |
| `og:type` / `og:url` / `og:image` | `@section('og_type'/'og_url'/'og_image', …)` | `website` / الرابط الحالي / الشعار |

### لماذا `@hasSection` في وسمَي og:title/og:description

`@push` يضيف الوسم **بعد** وسم التخطيط في الـ `<head>`، ومعظم المحلّلات (فيسبوك/واتساب)
تأخذ **الأول**. فكانت `og_title` التي يضبطها الأدمن في `SeoMeta` مُهمَلة تمامًا. المنفذان
الجديدان يجعلانها تعمل فعلًا بلا ازدواج.

---

## 2. البيانات المنظّمة (JSON-LD)

| النوع | الملف | ملاحظات |
|---|---|---|
| `Organization` | `layouts/app.blade.php` | `@id = {site}/#organization`، `sameAs` من روابط السوشيال غير الفارغة فقط |
| `WebSite` | `layouts/app.blade.php` | `@id = {site}/#website`، `publisher` يشير إلى المنظمة، **`alternateName`** من `common.brand_alt` |
| `BreadcrumbList` | `components/breadcrumb-ld.blade.php` | مكوّن مشترك — الكتب والأقسام والصفحات |
| `BlogPosting` + `BreadcrumbList` | `blog/show.blade.php` | **قديم ومستقل** — لم يُنقل إلى المكوّن |
| `Product` + `Book` | `books/show.blade.php` | مشروط بالسعر — انظر أدناه |

### `alternateName` — الحارس ضروري

```php
$seoBrandAlt = __('common.brand_alt');
$seoAltNames = is_array($seoBrandAlt) ? array_values(array_filter($seoBrandAlt, 'filled')) : [];
```

`__()` يُعيد **اسم المفتاح نصًّا** حين يغيب (لغة بلا ترجمة). بلا `is_array` كان سيُصدر
`alternateName: "common.brand_alt"` — اسم علامة وهمي في نتائج البحث.

### `Product` مشروط بالسعر — لا تُزل الشرط

```php
'@type' => $hasPrice ? ['Product', 'Book'] : 'Book',
```

مواصفة Google تشترط على `Product` **أحد ثلاثة**: `offers` أو `review` أو
`aggregateRating`. نحن لا نُصدر الأخيرين، وكتاب بلا سعر (مثل **BOOK1 «أنا لستُ شقيًا!»**)
لا يملك `offers`. جعله `Product` = خطأ معلَن في Search Console. `Book` وحده وسم صحيح
لأنه ليس نوع نتيجة غنية عند Google.

### ⚠️ `aggregateRating` — متروك عمدًا

**لا تضفه من `$book->reviews`.** `BookController::show` يجلب المراجعات بـ `->take(6)`،
فالحساب منها يُنتج `reviewCount` مقصوصًا عند ٦ ومتوسطًا مغلوطًا — وهو بالضبط نوع
التعارض الذي يجلب إجراءً يدويًا. عند توفّر مراجعات حقيقية استخدم الحقول المُجهَّزة:
`$book->avg_rating` و`$book->reviews_count`.

---

## 3. الفهرسة: `noindex` مقابل `robots.txt`

**القاعدة الموثّقة: لإبقاء صفحة خارج الفهرس، اسمح بالزحف + `noindex`.**

الحجب بـ `robots.txt` يمنع الزحف، فلا يرى Google وسم `noindex` **أصلًا** — ويظل قادرًا
على فهرسة الرابط عاريًا إن أشار إليه رابط خارجي. الجمع بينهما يُبطل نفسه.

- **`noindex` (ويُسمح بزحفها):** `/cart`، `/checkout`، `/search`، `/orders/*`
- **محجوبة في `robots.txt`:** `/admin`، `/search/suggest`، `/search/index.json`،
  `/coupon/`، `/inquiries` — نقاط AJAX/POST لا تُزار بمتصفح ولا قيمة لزحفها.

> ميزانية الزحف غير معنية هنا إطلاقًا (~٩٠ رابطًا).

---

## 4. توحيد المضيف

`www` كان **نسخة كاملة مكرّرة** من الموقع، وكل مضيف يجعل نفسه canonical لأن
`url()->current()` يبني الرابط من ترويسة `Host` الواردة. عولج بطبقتين:

1. `public/.htaccess` — 301 من `www` إلى النطاق المجرّد (يعالج **الطلب**).
2. `AppServiceProvider::boot()` — `URL::forceRootUrl(config('seo.site_url'))` +
   `forceScheme('https')` في الإنتاج فقط (يعالج **ما نولّده نحن**: canonical/sitemap/OG).

> **متبقٍّ على المالك:** شهادة TLS الحالية **لا تغطّي `www`**. يجب إضافته إلى SAN في
> hPanel، وإلا استقبل الزائر تحذير شهادة قبل أن تعمل إعادة التوجيه على HTTPS.
> البديل: حذف سجل `www` من DNS نهائيًا.

---

## 5. `/offers` — لماذا صار مسار 200

كان `Route::redirect('/offers', '/books?sale=1')`، ووجهته تُصدر `canonical = /books`
لأن `url()->current()` يحذف الـ query string — فكانت الصفحة **تُلغي نفسها** من الفهرس
رغم أن عنوانها و`<h1>` مختلفان.

### ⚠️ الفلتر يُدمج في طلبين، لا واحد

```php
if ($request->routeIs('books.offers')) {
    $request->merge(['sale' => 1]);   // للاستعلام
    request()->merge(['sale' => 1]);  // للقوالب
}
```

الحاوية تُنشئ الـ `FormRequest` عبر `FormRequest::createFrom()` الذي يبني `InputBag`
**جديدة**، فيبقى الطلب المربوط في الحاوية منفصلًا. الاستعلام يقرأ `$request`، بينما
القوالب — خانة «العروض فقط» في `partials/filters.blade.php` — تقرأ `request()` المساعدة.
**بلا السطر الثاني** تظهر الخانة غير مؤشَّرة ويسقط الفلتر عند أول إرسال للنموذج.

ويحتاج كذلك فرعًا خاصًّا لوجهة النموذج في `catalog/index.blade.php`، وإلا أرسل إلى
`/books` فغادر الزائر صفحة العروض.

---

## 6. `catalog/index.blade.php` — تحذير: أربعة متحكّمات

هذا القالب يُصيَّر من **أربعة** متحكّمات بمجموعات متغيّرات مختلفة:

| المتحكّم | يمرّر `$category` | يمرّر `$series` | يمرّر `$searchTerm` |
|---|---|---|---|
| `BookController@index` (`/books`, `/offers`) | `null` | ✗ | `null` |
| `CategoryController@show` | ✓ | ✗ | `null` |
| `SeriesController@show` | `null` | ✓ | `null` |
| `SearchController@index` | `null` | **✗** | ✓ |

> **`$series` غير مُمرَّر من ثلاثة منها** — لذلك كل قراءة له تستخدم `$series ?? null`.
> أي كود جديد في هذا القالب يجب أن يفترض غياب المتغيّر، لا وجوده.

---

## 7. الأيقونة

`public/favicon.ico` كان **صفر بايت**، والأيقونة المعلنة `logo.webp` (200×145) —
**WebP غير مدعوم** كأيقونة عند Google، والصورة غير مربّعة.

الآن: `favicon.ico` متعدد الأحجام (16→256) + `icon-512.png` + `icon-192.png`، مولَّدة من
`logo-1.png` (597×453) على خلفية بيضاء.

> **الروابط ثابتة عمدًا** — تغييرها يُبطل ما خزّنه Google ويؤخّر ظهور الأيقونة شهورًا.
>
> **ملاحظة تصميمية:** الشعار wordmark عريض صُغِّر داخل مربّع. مقروء عند 48px+ (وهو ما
> يعرضه Google) لكنه مزدحم عند 16px. رمز مربّع مخصّص من مصمّم سيكون أوضح.

---

## 8. ما لم يُختبر بعد

| البند | الحالة |
|---|---|
| `php artisan test` | **لم يُشغَّل** — لا `vendor/` محليًا. يُشغَّل على الخادم. |
| تصيير الصفحات فعليًا | **لم يُشغَّل**. المفحوص: `php -l` على كل ملف وكل كتلة Blade + اختبارات تشغيل لمولّدات JSON-LD الثلاثة. |
| Rich Results Test | **مطلوب بعد النشر** لكل من: كتاب بسعر، كتاب بلا سعر، قسم، صفحة CMS. |
| كاش الـ sitemap | يجب مسح مفتاح `seo.sitemap.xml` بعد النشر (TTL ساعة). |

---

## 9. متبقٍّ على المالك (لا يفعله الكود)

1. **Search Console** — تحقّق DNS TXT، أرسل `sitemap.xml`، ثم Request Indexing.
   **الأولوية القصوى**؛ فحص `site:qasaqis.store` أعاد صفر نتائج.
2. **شهادة SAN لـ `www`** (أو حذف سجل DNS) — انظر §4.
3. **أوصاف الأقسام** — المخطّط والقالب يدعمانها (`categories.description`) لكنها فارغة.
   تُكتب **من لوحة الإدارة**: الإنتاج فيه **٧ أقسام** لا ٦ كما في البذور، والمحتوى
   مُدار من الأدمن، فزرعها بـ `updateOrCreate` قد يدهس نصًّا كتبه المالك.
4. **بناء طلب اسمي** على «قصاقيص أطفال» — الرافعة الحقيقية الوحيدة لـ Sitelinks.

---

## 10. القاعدة الذهبية

**لا تغيّر الـ slugs.** تغيير بنية الروابط يُصفّر ثقة Google البنيوية ويعيد الموقع
إلى الصفر. هذا أكبر خطر على موقع في عمر هذا الموقع.
