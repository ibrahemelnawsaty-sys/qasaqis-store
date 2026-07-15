<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Country;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

/**
 * الدول المدعومة (M5). firstOrCreate يحافظ على تخصيص الأدمن عند إعادة البذر.
 * تعتمد على ShippingZoneSeeder (يسبقها في DatabaseSeeder). كل دولة مربوطة بمنطقة.
 */
class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $zoneIds = ShippingZone::query()->pluck('id', 'code');

        // [iso, name_ar, name_en, dial, zone_code, sort]
        $countries = [
            ['EG', 'مصر', 'Egypt', '+20', 'EG', 1],

            ['SA', 'السعودية', 'Saudi Arabia', '+966', 'GULF', 10],
            ['AE', 'الإمارات', 'United Arab Emirates', '+971', 'GULF', 11],
            ['KW', 'الكويت', 'Kuwait', '+965', 'GULF', 12],
            ['QA', 'قطر', 'Qatar', '+974', 'GULF', 13],
            ['BH', 'البحرين', 'Bahrain', '+973', 'GULF', 14],
            ['OM', 'عُمان', 'Oman', '+968', 'GULF', 15],

            ['JO', 'الأردن', 'Jordan', '+962', 'LEVANT', 20],
            ['LB', 'لبنان', 'Lebanon', '+961', 'LEVANT', 21],
            ['PS', 'فلسطين', 'Palestine', '+970', 'LEVANT', 22],
            ['IQ', 'العراق', 'Iraq', '+964', 'LEVANT', 23],
            ['SY', 'سوريا', 'Syria', '+963', 'LEVANT', 24],

            ['MA', 'المغرب', 'Morocco', '+212', 'NORTH_AFRICA', 30],
            ['DZ', 'الجزائر', 'Algeria', '+213', 'NORTH_AFRICA', 31],
            ['TN', 'تونس', 'Tunisia', '+216', 'NORTH_AFRICA', 32],
            ['LY', 'ليبيا', 'Libya', '+218', 'NORTH_AFRICA', 33],
            ['SD', 'السودان', 'Sudan', '+249', 'NORTH_AFRICA', 34],

            ['US', 'الولايات المتحدة', 'United States', '+1', 'INTL', 40],
            ['GB', 'المملكة المتحدة', 'United Kingdom', '+44', 'INTL', 41],
            ['DE', 'ألمانيا', 'Germany', '+49', 'INTL', 42],
            ['FR', 'فرنسا', 'France', '+33', 'INTL', 43],
            ['CA', 'كندا', 'Canada', '+1', 'INTL', 44],
        ];

        foreach ($countries as [$iso, $nameAr, $nameEn, $dial, $zoneCode, $sort]) {
            $zoneId = $zoneIds[$zoneCode] ?? null;

            if ($zoneId === null) {
                continue; // منطقة غير مبذورة — تخطٍّ آمن.
            }

            Country::query()->firstOrCreate(
                ['iso_code' => $iso],
                [
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'dial_code' => $dial,
                    'shipping_zone_id' => $zoneId,
                    'sort_order' => $sort,
                    'is_active' => true,
                ]
            );
        }
    }
}
