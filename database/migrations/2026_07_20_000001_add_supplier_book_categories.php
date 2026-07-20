<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * أقسام موضوعية وشكلية جديدة يحتاجها كتالوج المورّدين (دار رحيق: 101 عنوانًا)،
 * تُكمّل الأقسام الستّة الثابتة (الدستور 0.3) ولا تكرّرها.
 *
 * ثلاثة موضوعية لا يغطّيها أي قسم قائم (تربية مالية، بيئة، برمجة)، وثلاثة شكلية
 * تتقاطع مع الموضوع عبر العلاقة المتعدّدة book_category (أنشطة، تفاعلي، باقات).
 *
 * idempotent: updateOrInsert على slug، فإعادة التشغيل لا تُكرّر ولا تدهس تعديلات
 * الأدمن على الاسم/اللون (نكتب فقط عند غياب الصفّ).
 */
return new class extends Migration
{
    /** @var list<array{name:string,slug:string,color_hex:string,sort:int}> */
    private const CATEGORIES = [
        ['name' => 'التربية المالية',   'slug' => 'financial-literacy', 'color_hex' => '#1E9E6A', 'sort' => 7],
        ['name' => 'البيئة والاستدامة', 'slug' => 'environment',        'color_hex' => '#12B3A6', 'sort' => 8],
        ['name' => 'البرمجة والتقنية',  'slug' => 'programming',        'color_hex' => '#3B6FE0', 'sort' => 9],
        ['name' => 'كتب الأنشطة',       'slug' => 'activity-books',     'color_hex' => '#FF8A2A', 'sort' => 10],
        ['name' => 'كتب تفاعلية',       'slug' => 'interactive-books',  'color_hex' => '#EC4E96', 'sort' => 11],
        ['name' => 'باقات وسلاسل',      'slug' => 'bundles',            'color_hex' => '#8A5CD6', 'sort' => 12],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (self::CATEGORIES as $c) {
                // لا نكتب فوق صفّ موجود: قد يكون الأدمن غيّر اسمه أو لونه.
                $exists = DB::table('categories')->where('slug', $c['slug'])->exists();

                if (! $exists) {
                    DB::table('categories')->insert([
                        'name' => $c['name'],
                        'slug' => $c['slug'],
                        'parent_id' => null,
                        'color_hex' => $c['color_hex'],
                        'sort_order' => $c['sort'],
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // نحذف فقط الأقسام الفارغة التي أنشأناها — لا نحذف قسمًا رُبطت به كتب
        // (يترك category_id معلّقًا أو يكسر FK)، ولا قسمًا لمسه الأدمن.
        foreach (self::CATEGORIES as $c) {
            $id = DB::table('categories')->where('slug', $c['slug'])->value('id');

            if ($id === null) {
                continue;
            }

            $hasBooks = DB::table('books')->where('category_id', $id)->exists()
                || DB::table('book_category')->where('category_id', $id)->exists();

            if (! $hasBooks) {
                DB::table('categories')->where('id', $id)->delete();
            }
        }
    }
};
