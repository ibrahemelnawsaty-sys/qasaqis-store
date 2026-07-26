<?php

declare(strict_types=1);

use App\Models\Article;
use Illuminate\Database\Migrations\Migration;

/**
 * ٢٠ مقالًا طويل الذيل (٥ لكل قسم مدوّنة: نصائح تربوية، تربية بالقصص، مراجعات كتب،
 * أنشطة وتعليم) لبناء السلطة الموضوعية وجلب زيارات مبكرة (بحث SEO 2026-07-26).
 * المحتوى في database/seed/blog-articles-extra.json (JSON يتكفّل بالهروب الآمن للعربية/HTML).
 *
 * firstOrCreate على slug: يضيف الجديد فقط ولا يمسّ أي مقال قائم. المحتوى HTML آمن
 * (وسوم HtmlSanitizer المسموحة فقط، بلا روابط). الأغلفة null يرفعها المالك لاحقًا.
 */
return new class extends Migration
{
    private const AUTHOR = 'فريق قصاقيص';

    public function up(): void
    {
        foreach ($this->load() as $i => $a) {
            if (empty($a['slug']) || empty($a['title'])) {
                continue;
            }

            Article::firstOrCreate(
                ['slug' => $a['slug']],
                [
                    'title' => $a['title'],
                    'excerpt' => $a['excerpt'] ?? '',
                    'content' => $a['content'] ?? '',
                    'focus_keyword' => $a['focus_keyword'] ?? null,
                    'author_name' => self::AUTHOR,
                    'category' => $a['category'],
                    'reading_minutes' => (int) ($a['reading_minutes'] ?? 5),
                    'seo_title' => $a['seo_title'] ?? null,
                    'seo_description' => $a['seo_description'] ?? null,
                    'is_published' => true,
                    'is_featured' => false,
                    // نشر مُوزَّع على أيام سابقة كي لا تبدو كلها منشورة في لحظة واحدة.
                    'published_at' => now()->subDays(($i % 20) + 1),
                    'sort_order' => 200 + $i,
                ],
            );
        }
    }

    public function down(): void
    {
        $slugs = array_values(array_filter(array_column($this->load(), 'slug')));

        if ($slugs !== []) {
            Article::whereIn('slug', $slugs)->forceDelete();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function load(): array
    {
        $path = database_path('seed/blog-articles-extra.json');

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? array_values($data) : [];
    }
};
