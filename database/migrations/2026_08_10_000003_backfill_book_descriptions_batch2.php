<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * أوصاف SEO لدفعةٍ ثانية من الكتب بلا وصف (كتب تربية/إسلاميّة + سلسلة أطفال «يا جدتي»).
 *
 * الأوصاف بُحِثت من الإنترنت بمصادر حقيقيّة (aseeralkotb/jarir/goodreads/… — مسجّلة في
 * الـPR) ثم كُتبت بأسلوب المتجر وطُهِّرت عبر HtmlSanitizer (p/br/strong). البيانات في ملفٍّ
 * مرافقٍ مرفوعٍ بالـgit (database/data/book-descriptions-2026-08-10.json).
 *
 * حارس أمان: يُحدَّث الكتاب فقط إن كان وصفه فارغًا (null/'') — فلا يُدهَس وصفٌ موجود.
 * المطابقة بالعنوان الحرفيّ (طوبِق مسبقًا على العناوين الفعليّة). لا عكس آليّ.
 */
return new class extends Migration
{
    public function up(): void
    {
        $path = database_path('data/book-descriptions-2026-08-10.json');

        if (! is_file($path)) {
            return;
        }

        $map = json_decode((string) file_get_contents($path), true);

        if (! is_array($map)) {
            return;
        }

        foreach ($map as $title => $html) {
            $title = trim((string) $title);
            $html = trim((string) $html);

            if ($title === '' || $html === '') {
                continue;
            }

            DB::table('books')
                ->whereNull('deleted_at')
                ->where('title', $title)
                ->where(function ($q): void {
                    $q->whereNull('long_description')->orWhere('long_description', '');
                })
                ->update(['long_description' => $html, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // عملية محتوى لمرّة واحدة — لا عكس آليّ (الوصف السابق كان فارغًا).
    }
};
