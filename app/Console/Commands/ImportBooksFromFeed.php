<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Book;
use App\Models\BookImage;
use App\Models\Category;
use App\Models\Publisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * استيراد جماعي لكتب مورّد/ناشر من ملف منتجاته الرسمي (تصدير Shopify: CSV أو products.json).
 *
 * لماذا ملف رسمي لا سحب من الموقع (بند 1.1): البيانات من المورّد نفسه، دقيقة ومصرّح
 * باستخدامها، ولا تنكسر عند تغيير تصميم موقعه.
 *
 * قواعد السلامة المطبَّقة (نتيجة مراجعة عدائية):
 *  - المخزون يُكتب عند الإنشاء فقط. إعادة التشغيل لا تمسّ مخزونك الحيّ إطلاقًا
 *    (إلا بـ --update-stock صراحةً) — وإلا لبِعت ما لا تملك.
 *  - التحديث لا يكتب إلا الحقول التي وفّرها الملف فعلًا، فلا تُمحى أوصافك المحرّرة.
 *  - السعر المشطوب يُمسح عند انتهاء الخصم (لا يبقى خصمًا وهميًا للأبد).
 *  - كل قيمة تُقصّ إلى حدّ عمودها قبل الحفظ، فلا يتوقّف الاستيراد في منتصفه.
 *  - العمر/الصفحات/ISBN تبقى NULL ما لم تكن في الملف — لا نخترع بيانات (بند 0.4).
 *  - الكتب تُستورد مسوّدات ما لم تمرّر --publish.
 *
 * إعادة التشغيل آمنة: المطابقة على sku (ثابت) ثم slug، مع withTrashed لأن فهارس
 * التفرّد تشمل المحذوف ناعمًا. تنزيل الصور يتطلّب `php artisan storage:link`.
 */
class ImportBooksFromFeed extends Command
{
    protected $signature = 'books:import
        {file : مسار ملف منتجات المورّد (تصدير Shopify بصيغة CSV أو products.json)}
        {--category= : slug أو id القسم الذي تُسنَد إليه الكتب المستوردة (إلزامي)}
        {--publisher= : اسم دار النشر (افتراضيًا يؤخذ Vendor من الملف)}
        {--stock=5 : كمية المخزون للكتب الجديدة فقط}
        {--update-stock : اسمح بإعادة ضبط مخزون الكتب الموجودة أيضًا (خطر: يدهس مخزونك الحيّ)}
        {--limit=0 : استيراد أول N منتجًا فقط (0 = الكل)}
        {--publish : نشر الكتب فورًا (الافتراضي: مسوّدات)}
        {--with-images : تنزيل صور الغلاف والمعرض}
        {--force-images : إعادة تنزيل الصور حتى لو كانت موجودة}
        {--dry-run : تحليل وعرض النتيجة بلا أي كتابة}';

    protected $description = 'استيراد كتب مورّد من ملف منتجاته (Shopify CSV/JSON) — قابل لإعادة التشغيل بأمان.';

    private const PUBLIC_DISK = 'public';

    private const MAX_TITLE = 200;

    private const MAX_SKU = 60;

    private const MAX_SLUG = 200;

    private const MAX_SMALLINT = 65535;

    /** @var array<string, string> sku => العنوان الذي استهلكه في هذا التشغيل (كشف التصادم). */
    private array $seenSkus = [];

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! File::isFile($path)) {
            $this->error("الملف غير موجود: {$path}");

            return self::FAILURE;
        }

        $category = $this->resolveCategory();

        if (! $category) {
            return self::FAILURE;
        }

        try {
            $products = $this->parseFeed($path);
        } catch (\Throwable $e) {
            $this->error('تعذّرت قراءة الملف: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($products === []) {
            $this->error('لم يُعثر على أي منتج في الملف.');

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $products = array_slice($products, 0, $limit);
        }

        $dryRun = (bool) $this->option('dry-run');
        $withImages = (bool) $this->option('with-images');

        if ($withImages && ! File::exists(public_path('storage'))) {
            $this->warn('تنبيه: شغّل `php artisan storage:link` وإلا لن تُخدَم الصور على /storage.');
        }

        $this->info(sprintf(
            '%s %d منتجًا | القسم: %s | مخزون الجديد: %d | الحالة: %s%s',
            $dryRun ? '[تجريبي] سيُعالَج' : 'سيُستورَد',
            count($products),
            $category->name,
            (int) $this->option('stock'),
            $this->option('publish') ? 'منشور' : 'مسوّدة',
            $this->option('update-stock') ? ' | سيُعاد ضبط مخزون الموجود ⚠' : '',
        ));
        $this->newLine();

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'images' => 0, 'imageFails' => 0, 'noPrice' => 0];

        foreach ($products as $product) {
            // منتج واحد فاسد يجب ألّا يُسقط الدفعة كلها في منتصفها (كل منتج في معاملة
            // مستقلة، فما نجح يبقى) — نُبلغ عنه بوضوح ونكمل.
            try {
                $this->importProduct($product, $category, $dryRun, $withImages, $stats);
            } catch (\Throwable $e) {
                $stats['failed']++;
                $this->error(sprintf(
                    '  ✗ فشل «%s» [%s]: %s',
                    Str::limit((string) $product['title'], 40),
                    $product['handle'],
                    $e->getMessage(),
                ));
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'تمّ. جديد: %d | مُحدَّث: %d | متخطّى: %d | فشل: %d | صور: %d (فشل %d).',
            $stats['created'], $stats['updated'], $stats['skipped'], $stats['failed'], $stats['images'], $stats['imageFails'],
        ));

        if ($stats['noPrice'] > 0) {
            $this->warn(sprintf('%d منتجًا بلا سعر صالح — تحقّق من عمود Variant Price في الملف.', $stats['noPrice']));
        }

        if ($dryRun) {
            $this->warn('تشغيل تجريبي — لم يُكتب أي شيء في قاعدة البيانات.');
        } elseif (! $this->option('publish')) {
            $this->warn('الكتب حُفظت كمسوّدات. راجعها من لوحة الأدمن ثم فعّل «منشور».');
        }

        return self::SUCCESS;
    }

    private function resolveCategory(): ?Category
    {
        $key = trim((string) $this->option('category'));

        if ($key === '') {
            $this->error('الخيار --category إلزامي. مثال: --category=stories');
            $this->line('الأقسام المتاحة: '.Category::query()->pluck('slug')->implode(', '));

            return null;
        }

        $category = Category::query()
            ->where('slug', $key)
            ->when(ctype_digit($key), fn ($q) => $q->orWhere('id', (int) $key))
            ->first();

        if (! $category) {
            $this->error("لا يوجد قسم بالمعرّف: {$key}");
            $this->line('الأقسام المتاحة: '.Category::query()->pluck('slug')->implode(', '));
        }

        return $category;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseFeed(string $path): array
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'json'
            ? $this->parseJson($path)
            : $this->parseCsv($path);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseJson(string $path): array
    {
        /** @var array<string, mixed>|array<int, mixed> $data */
        $data = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        $items = $data['products'] ?? $data;
        $out = [];

        foreach ((array) $items as $p) {
            if (! is_array($p) || blank($p['title'] ?? null)) {
                continue;
            }

            // المتغيّر الأول كوحدة واحدة: السعر والمشطوب من نفس المتغيّر (لا خلط).
            $variant = (array) (($p['variants'][0]) ?? []);

            $out[] = [
                'handle' => (string) ($p['handle'] ?? ''),
                'title' => (string) $p['title'],
                'body_html' => (string) ($p['body_html'] ?? ''),
                'vendor' => (string) ($p['vendor'] ?? ''),
                'sku' => (string) ($variant['sku'] ?? ''),
                'price' => $this->toDecimal($variant['price'] ?? null),
                'compare_at' => $this->toDecimal($variant['compare_at_price'] ?? null),
                'grams' => (int) ($variant['grams'] ?? 0),
                'images' => array_values(array_unique(array_filter(array_map(
                    static fn ($img): string => is_array($img) ? (string) ($img['src'] ?? '') : (string) $img,
                    (array) ($p['images'] ?? []),
                )))),
            ];
        }

        return $out;
    }

    /**
     * تصدير Shopify يضع صفًّا لكل متغيّر/صورة، وحقول المنتج في أول صفّ لكل Handle.
     * نجمّع حسب Handle، ونلتقط حقول المتغيّر **كوحدة واحدة** من أول صفّ يحمل سعرًا/SKU
     * حتى لا نخلط سعر متغيّر بسعر مشطوب من متغيّر آخر (فنختلق خصمًا).
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseCsv(string $path): array
    {
        $fh = fopen($path, 'rb');

        if ($fh === false) {
            throw new \RuntimeException('تعذّر فتح ملف CSV.');
        }

        // escape فارغ = التزام RFC-4180 كما يُصدِّر Shopify؛ الافتراضي (\) يبتلع صفوفًا.
        $header = fgetcsv($fh, 0, ',', '"', '');

        if ($header === false) {
            fclose($fh);

            throw new \RuntimeException('ملف CSV فارغ.');
        }

        $header[0] = preg_replace('/^\x{FEFF}/u', '', (string) $header[0]) ?? $header[0];
        $header = array_map(static fn ($h): string => trim((string) $h), $header);

        // ملف ليس تصدير Shopify: نفشل بوضوح بدل استيراد كتالوج مشوّه ونعلن النجاح.
        foreach (['Handle', 'Title'] as $required) {
            if (! in_array($required, $header, true)) {
                fclose($fh);

                throw new \RuntimeException("عمود مطلوب مفقود: «{$required}». تأكّد أن الملف تصدير منتجات Shopify.");
            }
        }

        foreach (['Variant Price', 'Image Src'] as $optional) {
            if (! in_array($optional, $header, true)) {
                $this->warn("تنبيه: العمود «{$optional}» غير موجود — ستُستورَد المنتجات بدونه.");
            }
        }

        /** @var array<string, array<string, mixed>> $grouped */
        $grouped = [];

        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }

            $row = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), ''));
            $key = trim((string) ($row['Handle'] ?? ''));

            if ($key === '') {
                continue;
            }

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'handle' => $key, 'title' => '', 'body_html' => '', 'vendor' => '',
                    'sku' => '', 'price' => null, 'compare_at' => null, 'grams' => 0,
                    'images' => [], 'variantLocked' => false,
                ];
            }

            $g = &$grouped[$key];

            $g['title'] = $g['title'] !== '' ? $g['title'] : trim((string) ($row['Title'] ?? ''));
            $g['body_html'] = $g['body_html'] !== '' ? $g['body_html'] : (string) ($row['Body (HTML)'] ?? '');
            $g['vendor'] = $g['vendor'] !== '' ? $g['vendor'] : trim((string) ($row['Vendor'] ?? ''));

            // وحدة المتغيّر: تُلتقط مرة واحدة من أول صفّ يحمل SKU أو سعرًا.
            if (! $g['variantLocked']) {
                $sku = trim((string) ($row['Variant SKU'] ?? ''));
                $price = $this->toDecimal($row['Variant Price'] ?? null);

                if ($sku !== '' || $price !== null) {
                    $g['sku'] = $sku;
                    $g['price'] = $price;
                    $g['compare_at'] = $this->toDecimal($row['Variant Compare At Price'] ?? null);
                    $g['grams'] = (int) ($row['Variant Grams'] ?? 0);
                    $g['variantLocked'] = true;
                }
            }

            $src = trim((string) ($row['Image Src'] ?? ''));

            if ($src !== '' && ! in_array($src, $g['images'], true)) {
                $g['images'][] = $src;
            }

            unset($g);
        }

        fclose($fh);

        return array_values(array_filter($grouped, static fn (array $p): bool => $p['title'] !== ''));
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, int>  $stats
     */
    private function importProduct(array $product, Category $category, bool $dryRun, bool $withImages, array &$stats): void
    {
        // القصّ إلى حدود الأعمدة قبل أي شيء — قيمة زائدة توقف الاستيراد في منتصفه.
        $title = mb_substr(trim((string) $product['title']), 0, self::MAX_TITLE);
        $sku = mb_substr(trim((string) $product['sku']), 0, self::MAX_SKU) ?: null;
        $slug = $this->buildSlug($product);
        $price = $product['price'];

        if ($price === null) {
            $stats['noPrice']++;
        }

        // تصادم SKU داخل نفس الملف: منتجان بنفس الرمز كانا سيندمجان في كتاب واحد.
        if ($sku !== null && isset($this->seenSkus[$sku])) {
            $this->warn(sprintf('  ! متخطّى (SKU مكرّر «%s» يستخدمه: %s): %s', $sku, Str::limit($this->seenSkus[$sku], 30), Str::limit($title, 34)));
            $stats['skipped']++;

            return;
        }

        $existing = Book::withTrashed()
            ->when($sku !== null, fn ($q) => $q->where('sku', $sku))
            ->when($sku === null, fn ($q) => $q->where('slug', $slug))
            ->first();

        if ($existing === null && $sku !== null) {
            $existing = Book::withTrashed()->where('slug', $slug)->first();
        }

        if ($existing !== null && $existing->trashed()) {
            $this->warn(sprintf('  ! متخطّى (محذوف سابقًا): %s', Str::limit($title, 44)));
            $stats['skipped']++;

            return;
        }

        // لا slug بديل «-2»: كتاب بمُعرّف مختلف لن تُطابقه الجولة القادمة فتتكاثر النسخ.
        // نُبلغ عن التعارض ليحلّه الأدمن بدل إنشاء نسخة يتيمة.
        if ($existing === null && Book::withTrashed()->where('slug', $slug)->exists()) {
            $this->warn(sprintf('  ! متخطّى (المُعرّف «%s» مستخدم لكتاب آخر): %s', $slug, Str::limit($title, 34)));
            $stats['skipped']++;

            return;
        }

        if ($sku !== null) {
            $this->seenSkus[$sku] = $title;
        }

        if ($dryRun) {
            $this->line(sprintf(
                '  %s %s  [%s]  %s',
                $existing ? '~' : '+',
                Str::limit($title, 44),
                $slug,
                $price !== null ? $price.' ج.م' : 'بلا سعر',
            ));
            $existing ? $stats['updated']++ : $stats['created']++;

            return;
        }

        $publisher = $this->resolvePublisher((string) $product['vendor']);
        $bodyHtml = (string) $product['body_html'];

        // حقول المحتوى: فقط ما وفّره الملف فعلًا — فلا تُمحى بياناتك المحرّرة بـ NULL.
        $content = ['title' => $title];

        if ($sku !== null) {
            $content['sku'] = $sku;
        }

        if ($bodyHtml !== '') {
            $content['long_description'] = $bodyHtml;
            $content['short_description'] = $this->plainSummary($bodyHtml);
        }

        if ($price !== null) {
            $content['price'] = $price;
            // يُضبط أو يُمسح صراحةً: خصم منتهٍ لا يبقى سعرًا مشطوبًا مضلِّلًا.
            $compare = $product['compare_at'];
            $content['old_price'] = ($compare !== null && (float) $compare > (float) $price) ? $compare : null;
        }

        if ((int) $product['grams'] > 0) {
            $content['weight_grams'] = min((int) $product['grams'], self::MAX_SMALLINT);
        }

        if ($publisher) {
            $content['publisher_id'] = $publisher->id;
        }

        $book = DB::transaction(function () use ($existing, $content, $category, $slug): Book {
            if ($existing) {
                $update = $content;

                // المخزون لا يُمسّ عند التحديث إلا بطلب صريح (يمنع البيع الزائد).
                if ($this->option('update-stock')) {
                    $update += $this->stockAttributes();
                }

                // النشر: نرفع العلم، ونختم التاريخ مرة واحدة فقط (لا نعيد ضبطه كل جولة).
                if ($this->option('publish')) {
                    $update['is_published'] = true;

                    if ($existing->published_at === null) {
                        $update['published_at'] = now();
                    }
                }

                $existing->fill($update)->save();

                return $existing;
            }

            $create = $content + $this->stockAttributes() + [
                'category_id' => $category->id,
                'slug' => $slug,
                'is_published' => (bool) $this->option('publish'),
                'published_at' => $this->option('publish') ? now() : null,
            ];

            return Book::create($create);
        });

        $existing ? $stats['updated']++ : $stats['created']++;
        $this->line(sprintf('  %s %s', $existing ? '~ حُدّث' : '+ أُنشئ', Str::limit($title, 50)));

        if ($withImages && $product['images'] !== []) {
            $this->importImages($book, (array) $product['images'], $stats);
        }
    }

    /**
     * حالة المخزون تُشتق من الكمية — --stock=0 يعني «غير متوفر» لا «متوفر».
     *
     * @return array<string, mixed>
     */
    private function stockAttributes(): array
    {
        $qty = max(0, (int) $this->option('stock'));

        return [
            'stock_quantity' => $qty,
            'stock_status' => $qty > 0 ? 'in_stock' : 'out_of_stock',
            'manage_stock' => true,
        ];
    }

    /**
     * @param  array<int, string>  $urls
     * @param  array<string, int>  $stats
     */
    private function importImages(Book $book, array $urls, array &$stats): void
    {
        $force = (bool) $this->option('force-images');
        $coverPath = null;

        foreach (array_values($urls) as $i => $url) {
            $isCover = $i === 0;
            $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
            $dest = $isCover
                ? "books/{$book->slug}/cover.{$ext}"
                : "books/{$book->slug}/gallery-{$i}.{$ext}";

            $alreadyOnDisk = Storage::disk(self::PUBLIC_DISK)->exists($dest);

            if (! $alreadyOnDisk || $force) {
                try {
                    $response = Http::timeout(25)->get($url);

                    if (! $response->successful()) {
                        $this->warn("    تعذّر تنزيل صورة ({$response->status()}): {$url}");
                        $stats['imageFails']++;

                        continue;
                    }

                    Storage::disk(self::PUBLIC_DISK)->put($dest, $response->body());
                    $stats['images']++;
                } catch (\Throwable $e) {
                    $this->warn('    فشل تنزيل صورة: '.$e->getMessage());
                    $stats['imageFails']++;

                    continue;
                }
            }

            // يُكتب صفّ الصورة دائمًا حتى لو كان الملف موجودًا مسبقًا — وجود الملف
            // ليس دليلًا على وجود السجل (كانت الصور تختفي من المعرض).
            BookImage::updateOrCreate(
                [
                    'book_id' => $book->id,
                    'collection' => $isCover ? 'cover' : 'gallery',
                    'sort_order' => $i,
                ],
                [
                    'path' => $dest,
                    'disk' => self::PUBLIC_DISK,
                    'is_cover' => $isCover,
                    'alt' => $book->title,
                ],
            );

            if ($isCover) {
                $coverPath = $dest;
            }
        }

        if ($coverPath !== null && $book->cover_image !== $coverPath) {
            $book->forceFill(['cover_image' => $coverPath])->save();
        }
    }

    private function resolvePublisher(string $vendor): ?Publisher
    {
        $name = mb_substr(trim((string) ($this->option('publisher') ?: $vendor)), 0, 190);

        if ($name === '') {
            return null;
        }

        // withTrashed: دار محذوفة ناعمًا تحتفظ باسمها/مُعرّفها، فإنشاء بديل يكرّرها.
        $existing = Publisher::withTrashed()->where('name', $name)->first();

        if ($existing) {
            if ($existing->trashed()) {
                $this->warn("    دار النشر «{$name}» محذوفة — تُرك حقل الناشر فارغًا.");

                return null;
            }

            return $existing;
        }

        $slug = Str::slug($name) ?: 'publisher-'.substr(md5($name), 0, 8);

        return Publisher::create([
            'name' => $name,
            'slug' => $this->uniqueSlug(Publisher::class, $slug),
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    /**
     * slug لاتيني ثابت: handle ← sku ← بصمة (العربية تُنتج slug فارغًا).
     *
     * @param  array<string, mixed>  $product
     */
    private function buildSlug(array $product): string
    {
        $slug = Str::slug((string) $product['handle']);

        if ($slug === '') {
            $slug = Str::slug((string) $product['sku']);
        }

        if ($slug === '') {
            $seed = (string) ($product['handle'] ?: $product['title']);
            $slug = 'book-'.substr(md5($seed), 0, 10);
        }

        return mb_substr($slug, 0, self::MAX_SLUG);
    }

    /**
     * أول slug حرّ (للكيانات التي يجوز فيها التوليد، كدار النشر). يتجاوز النطاقات
     * العامة لأن فهرس التفرّد يشمل المحذوف ناعمًا.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function uniqueSlug(string $model, string $slug): string
    {
        $base = $slug !== '' ? $slug : 'item';
        $candidate = $base;
        $i = 2;

        while ($model::query()->withoutGlobalScopes()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    private function plainSummary(string $html): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));

        return $text !== '' ? Str::limit($text, 480) : null;
    }

    /**
     * يقبل صيغ الأرقام الشائعة في التصديرات (مسافات، فواصل آلاف، فاصلة عشرية،
     * رموز عملة) بدل إسقاطها بصمت وإنتاج كتالوج بلا أسعار.
     */
    private function toDecimal(mixed $value): ?string
    {
        if ($value === null || is_bool($value) || is_array($value)) {
            return null;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        // إزالة كل ما ليس رقمًا أو فاصلة/نقطة أو إشارة (عملات، مسافات عادية وغير فاصلة).
        $clean = preg_replace('/[^\d,.\-]/u', '', $raw) ?? '';

        if ($clean === '') {
            return null;
        }

        // فاصلة عشرية أوروبية (1.234,56 أو 12,50) => نقطة؛ وإلا نُسقط فواصل الآلاف.
        if (str_contains($clean, ',') && (! str_contains($clean, '.') || strrpos($clean, ',') > strrpos($clean, '.'))) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }

        if (! is_numeric($clean)) {
            return null;
        }

        return number_format((float) $clean, 2, '.', '');
    }
}
