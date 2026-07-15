# المُجدول والطابور والنسخ الاحتياطي على Hostinger

> معلَم M1 — البنية التحتية التشغيلية. كل ما يلي يعتمد على **إدخال cron وحيد**.

## 1) إدخال cron الوحيد

في لوحة Hostinger/cPanel → **Cron Jobs**، أضف مهمة تعمل كل دقيقة:

```
* * * * * cd /home/USER/domains/qasaqis.store && php artisan schedule:run >> /dev/null 2>&1
```

> استبدل `USER` والمسار بمسار مشروعك الفعلي. هذا الإدخال **وحده** يشغّل كل المهام
> المجدولة في `bootstrap/app.php` (`withSchedule`).

## 2) ما الذي يشغّله هذا الإدخال

| المهمة | التوقيت (توقيت القاهرة) | الغرض |
|---|---|---|
| `backup:clean` | يوميًا 03:30 | حذف النسخ القديمة وفق سياسة الاحتفاظ |
| `backup:run --only-db` | يوميًا 03:45 | نسخة سريعة لقاعدة البيانات |
| `backup:run` | يوميًا 04:00 | نسخة كاملة (قاعدة البيانات + إثباتات الدفع) |
| `backup:monitor` | يوميًا 09:00 | التحقق من سلامة آخر نسخة والإشعار عند الخلل |
| `queue:work --stop-when-empty` | كل دقيقة | معالجة الطابور (الإشعارات) بلا Supervisor |

> **نقطة فشل واحدة:** تعطّل هذا الإدخال يوقف النسخ والطابور معًا. راقبه خارجيًا
> (انظر §5).

## 3) متغيّرات البيئة المطلوبة على السيرفر

```
# رصد الأخطاء
SENTRY_LARAVEL_DSN=<dsn من مشروع Sentry>
SENTRY_TRACES_SAMPLE_RATE=0.1
SENTRY_SEND_DEFAULT_PII=false

# النسخ الاحتياطي (Cloudflare R2)
BACKUP_ARCHIVE_PASSWORD=<كلمة مرور قوية لتشفير الأرشيف>   # إلزامي
BACKUP_NOTIFICATION_EMAIL=<بريد لإشعارات فشل النسخ>       # إلزامي
BACKUP_R2_ACCESS_KEY_ID=<من R2>
BACKUP_R2_SECRET_ACCESS_KEY=<من R2>
BACKUP_R2_BUCKET=<اسم الحاوية>
BACKUP_R2_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
BACKUP_R2_REGION=auto

# البريد الحقيقي (لوصول إشعارات فشل النسخ فعليًا — لا log)
MAIL_MAILER=smtp
```

> ⚠️ **إلزامي (لا اختياري):**
> - **`BACKUP_ARCHIVE_PASSWORD`**: بدونها يفشل `backup:run` في الإنتاج بحارس
>   صريح — كي لا تُرفع نسخة **غير مشفّرة** تحوي بيانات العملاء وإثباتات الدفع
>   (PII) إلى R2 (الدستور 3.4).
> - **`BACKUP_NOTIFICATION_EMAIL` + `MAIL_MAILER=smtp`**: إشعارات فشل النسخ
>   تصل بالبريد. توجد أيضًا **قناة إنذار مستقلة عبر Sentry** (تُرسَل رسالة
>   `[backup] ...` عند أي فشل/اعتلال) فلا يصمت الفشل لو تعطّل SMTP.
> - مفاتيح R2 يجب أن تكون **مقيّدة الصلاحية على حاوية النسخ فقط**. تسريبها
>   يمنح وصولًا لنسخة قاعدة البيانات كاملة — لذا التشفير خط الدفاع الأخير.

> **ملاحظة الطابور:** `DB_QUEUE_RETRY_AFTER` (افتراضي 90ث) يجب أن يبقى **أكبر**
> من `--max-time=55` لأمر `queue:work` كي لا تُلتقط مهمة جارية مرتين. إن أضفت
> مهامًا أطول مستقبلًا، ارفع القيمتين معًا.

> ⚠️ **`APP_URL` إلزامي وصحيح (M4):** إشعارات البريد ShouldQueue تُبنى في عامل
> الطابور بلا سياق HTTP، فروابطها الموقّعة وزر «فتح الطلب في اللوحة» تُشتقّ من
> `APP_URL`. إن بقي `http://localhost` (الافتراضي) فكل روابط البريد الموقّعة
> تُرفَض بـ 403 عند العميل وزر اللوحة يشير لـ localhost. اضبط
> `APP_URL=https://qasaqis.store` في `.env` الإنتاج (بيئة العامل ترثه).

## 4) التحقق بعد النشر

```bash
php artisan schedule:list                 # يجب أن تظهر كل المهام أعلاه
php artisan backup:run --only-db          # يتأكد من الوصول إلى R2
php artisan queue:work --stop-when-empty  # يتأكد من عمل الطابور
```

ثم ولّد استثناءً تجريبيًا وتأكد من وصوله إلى لوحة Sentry.

## 5) مراقبة uptime خارجية

اربط خدمة مراقبة (UptimeRobot/Better Uptime) على:

```
https://qasaqis.store/up
```

مع تنبيه بريد/واتساب عند التوقف — هذا هو إنذار تعطّل الـ cron/التطبيق.

## ملاحظات

- بعد `composer require` على السيرفر، شغّل مرة واحدة للتحقق من مطابقة الإعدادات
  المثبَّتة:
  `php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"`
  و `php artisan sentry:publish` (قارن الناتج بـ `config/backup.php` و`config/sentry.php`
  الموجودَين — لا تستبدلهما، فهما يحملان تخصيصات المشروع).
- البديل `QUEUE_CONNECTION=sync` يجعل الإرسال تزامنيًا لكنه **يحجب** إجراء الأدمن
  على SMTP — غير مُوصى به في الإنتاج.
