<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

/**
 * عملات العرض: القاعدة EGP + الخليج + الدولار. المعدّلات (egp_per_unit) **تقريبيّة
 * بهامش المالك** ويضبطها بنفسه من اللوحة لاحقًا — لذا firstOrCreate (لا نطمس تعديلاته
 * عند إعادة الزرع؛ نضيف الناقص فقط). reference_rate = السوق الحقيقيّ (للعرض/الهامش).
 */
class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        // code => [symbol, name_ar, egp_per_unit(بالهامش), reference_rate(الحقيقيّ), decimals, step, mode, is_base, sort]
        // القيم العشريّة كسلاسل (الحقول decimal) لتفادي تحذير BigNumber عند تمرير float.
        $currencies = [
            ['EGP', 'ج.م', 'جنيه مصريّ',    '1',    null,   0, '1',    'nearest', true,  0],
            ['SAR', 'ر.س', 'ريال سعوديّ',   '10',   '13',   2, '5',    'up',      false, 1],
            ['AED', 'د.إ', 'درهم إماراتيّ', '10.5', '13.3', 2, '5',    'up',      false, 2],
            ['QAR', 'ر.ق', 'ريال قطريّ',    '10.5', '13.4', 2, '5',    'up',      false, 3],
            ['KWD', 'د.ك', 'دينار كويتيّ',  '130',  '159',  3, '0.25', 'up',      false, 4],
            ['BHD', 'د.ب', 'دينار بحرينيّ', '105',  '130',  3, '0.25', 'up',      false, 5],
            ['OMR', 'ر.ع', 'ريال عمانيّ',   '100',  '127',  3, '0.25', 'up',      false, 6],
            ['USD', '$',   'دولار أمريكيّ', '40',   '49',   2, '0.5',  'up',      false, 7],
        ];

        foreach ($currencies as [$code, $symbol, $name, $rate, $ref, $decimals, $step, $mode, $isBase, $sort]) {
            Currency::query()->firstOrCreate(
                ['code' => $code],
                [
                    'symbol' => $symbol,
                    'name_ar' => $name,
                    'egp_per_unit' => $rate,
                    'reference_rate' => $ref,
                    'decimals' => $decimals,
                    'rounding_step' => $step,
                    'rounding_mode' => $mode,
                    'is_active' => true,
                    'is_base' => $isBase,
                    'sort_order' => $sort,
                ],
            );
        }
    }
}
