<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * قسم «ليه الأمهات بيحبونا» كان يخزّن إيموجي في العمود icon. صار يخزّن مفتاح
 * أيقونة من مكتبة resources/views/components/why-icon.blade.php.
 *
 * نحوّل الإيموجي الأربعة المزروعة فقط. أي قيمة أخرى (بطاقة أضافها الأدمن
 * بإيموجي من عنده) تُترك كما هي عمدًا — والمكوّن يطبعها نصًّا فتظلّ تعمل.
 * تخمين مفتاح لإيموجي لا نعرفه سيضع أيقونة خاطئة، وهو أسوأ من إبقاء الإيموجي.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const MAP = [
        '🎯' => 'target-curated',
        '🔤' => 'harakat-letter',
        '🎨' => 'pigment-sweep',
        '💰' => 'value-tag',
        '💛' => 'heart-care',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (self::MAP as $emoji => $key) {
                DB::table('why_items')->where('icon', $emoji)->update(['icon' => $key]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            foreach (self::MAP as $emoji => $key) {
                DB::table('why_items')->where('icon', $key)->update(['icon' => $emoji]);
            }
        });
    }
};
