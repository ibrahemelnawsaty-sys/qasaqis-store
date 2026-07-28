<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * قسم «اللغة العربية» (M13) — بطلب المالك: وجهة استيراد كتب اللغة/الفصحى من مورّد
 * معتمد عبر `php artisan books:import <feed> --category=arabic-language --with-images`.
 *
 * القيد 0.3 يمنع **حذف** الأقسام الستة، لا **إضافة** قسم يطلبه المالك صراحةً. القائمة
 * القانونية في CategorySeeder (لأي تنصيب جديد)؛ وهذه الهجرة تُنزِل القسم على إنتاج
 * مزروع سلفًا مع `migrate --force`. idempotent عبر updateOrInsert على slug (بلا موديل
 * كي لا تعتمد الهجرة على تعريف Category الذي قد يتغيّر لاحقًا).
 */
return new class extends Migration
{
    private const SLUG = 'arabic-language';

    public function up(): void
    {
        DB::table('categories')->updateOrInsert(
            ['slug' => self::SLUG],
            [
                'name' => 'اللغة العربية',
                'parent_id' => null,
                'color_hex' => '#12B3A6',   // توكن --teal (بند 0.1) — غير مستخدم لقسم آخر
                'sort_order' => 7,          // بعد الأقسام الستة
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        // لا نحذف قسمًا قد يحوي كتبًا مستوردة: books.category_id = restrictOnDelete،
        // فالحذف الأعمى إمّا يفشل أو ييتّم كتبًا. الإزالة تتمّ يدويًّا من الأدمن بعد
        // نقل كتب القسم إلى قسم آخر.
    }
};
