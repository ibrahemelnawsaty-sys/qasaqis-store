<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * إرشاد موسمي — «متى يكثر الطلب على أيّ نوع». المتجر جديد وتاريخ الطلبات قليل،
 * فأيّ ادّعاء موسمي مقيس يحتاج تراكمًا. لذلك هذه **طبقة توجيه عام** من معرفة السوق
 * المصري (لا من بيانات المتجر) — موسومة صراحةً — تُستبدَل تدريجيًّا بـ«أفضل كتب كل
 * موسم من بياناتك» بعد موسمين متماثلين. لا يستعلم عن أيّ عمود، فلا اختراع بيانات.
 *
 * الأقسام المذكورة موجودة فعلًا في المتجر (المزروعة الستّة + الجديدة). مرئيّ لمن
 * يملك products.view (تخطيط منتجات).
 */
class SeasonalGuideWidget extends Widget
{
    protected static ?int $sort = 9;

    protected static string $view = 'filament.widgets.seasonal-guide';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('products.view');
    }

    protected function getViewData(): array
    {
        return [
            'seasons' => [
                [
                    'icon' => '🌙',
                    'name' => 'رمضان وعيد الفطر',
                    'when' => 'يتحرّك سنويًّا (هجري)',
                    'note' => 'إقبال على القيم والقصص الدينية والهدايا العائلية.',
                    'cats' => ['كتب دينية', 'قصص', 'سلوكيات ومشاعر'],
                ],
                [
                    'icon' => '🎒',
                    'name' => 'العودة للمدارس',
                    'when' => 'أغسطس – سبتمبر',
                    'note' => 'ذروة الأنشطة والكتب العلمية والمهارات الجديدة.',
                    'cats' => ['كتب الأنشطة', 'كتب علمية', 'البرمجة والتقنية'],
                ],
                [
                    'icon' => '☀️',
                    'name' => 'الصيف والإجازة',
                    'when' => 'يونيو – أغسطس',
                    'note' => 'وقت القراءة الحرّة: قصص ومغامرات وروايات.',
                    'cats' => ['قصص', 'روايات'],
                ],
                [
                    'icon' => '🐑',
                    'name' => 'عيد الأضحى',
                    'when' => 'يتحرّك سنويًّا (هجري)',
                    'note' => 'موسم الهدايا — رتّب الباقات بالأكثر مبيعًا الفعلي.',
                    'cats' => ['باقات وسلاسل', 'قصص'],
                ],
            ],
        ];
    }
}
