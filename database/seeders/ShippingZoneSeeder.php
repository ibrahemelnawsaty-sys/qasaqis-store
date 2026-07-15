<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

/**
 * مناطق الشحن (M5). idempotent. flat_cost=0.00 مبدئيًا — يضبطها الأدمن من CMS
 * (لا نخترع أسعار شحن، بند 1.1). منطقة EG موجودة للاتساق لكن تسعير مصر مرجعه
 * config/egypt لا هذه القيمة.
 */
class ShippingZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            ['code' => 'EG', 'name_ar' => 'مصر', 'name_en' => 'Egypt', 'sort_order' => 1],
            ['code' => 'GULF', 'name_ar' => 'دول الخليج', 'name_en' => 'Gulf countries', 'sort_order' => 2],
            ['code' => 'LEVANT', 'name_ar' => 'بلاد الشام والعراق', 'name_en' => 'Levant & Iraq', 'sort_order' => 3],
            ['code' => 'NORTH_AFRICA', 'name_ar' => 'شمال أفريقيا', 'name_en' => 'North Africa', 'sort_order' => 4],
            ['code' => 'INTL', 'name_ar' => 'باقي دول العالم', 'name_en' => 'Rest of the world', 'sort_order' => 9],
        ];

        // firstOrCreate: لا يلمس صفًّا قائمًا عند إعادة البذر (يحافظ على flat_cost
        // وحالة التفعيل التي ضبطها الأدمن). flat_cost يأخذ افتراضي DB=0.00 عند الإنشاء.
        foreach ($zones as $zone) {
            ShippingZone::query()->firstOrCreate(
                ['code' => $zone['code']],
                [
                    'name_ar' => $zone['name_ar'],
                    'name_en' => $zone['name_en'],
                    'sort_order' => $zone['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
