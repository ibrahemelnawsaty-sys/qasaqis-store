<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Book;
use App\Support\Money;

/**
 * المصدر الوحيد لاشتقاق تكلفة كتاب (الدستور 2.3): تُستعمَل عند لقطة البيع
 * (PlaceOrderAction) وعند ترحيل الطلبات القديمة (CostBackfillService) فيبقى
 * المنطق واحدًا في مكان واحد.
 *
 * الأولوية:
 *   ١) سعر شراء مُدخَل يدويًا (books.cost_price) → تكلفة مؤكّدة (estimated=false).
 *   ٢) وإلا: تقدير = السعر × (١ − نسبة خصم دار النشر / ١٠٠) → estimated=true.
 *      نسبة الدار من publishers.cost_discount_percent؛ وحين لا نسبة له يُستعمل
 *      الافتراضي العام (config finance.default_cost_discount_percent).
 *   ٣) كتاب بلا سعر بيع (نادر — price مطلوب) → لا تقدير ممكن (amount=null).
 *
 * كل الحساب bcmath لا float (الدستور 3.5). النسبة تُقيَّد إلى [0,100] دفاعيًّا
 * فلا تكلفة سالبة مهما كان المُدخَل.
 */
class BookCostResolver
{
    /**
     * @return array{amount: ?string, estimated: bool}
     */
    public function resolve(Book $book): array
    {
        // ١) تكلفة مُدخَلة يدويًا تتقدّم دائمًا (تجاوز الأدمن).
        if ($book->cost_price !== null) {
            return ['amount' => Money::normalize($book->cost_price), 'estimated' => false];
        }

        // ٣) بلا سعر بيع لا يمكن التقدير.
        if ($book->price === null) {
            return ['amount' => null, 'estimated' => false];
        }

        // ٢) تقدير من خصم الدار، وإلا الافتراضي العام.
        $discount = $this->discountPercentFor($book);

        // العامل = (١٠٠ − النسبة) / ١٠٠، ثم التكلفة = السعر × العامل (bcmath).
        $factor = bcdiv(bcsub('100', $discount, 4), '100', 6);
        $amount = Money::normalize(bcmul(Money::normalize($book->price), $factor, 4));

        return ['amount' => $amount, 'estimated' => true];
    }

    /**
     * نسبة الخصم المطبَّقة للكتاب كسلسلة، مقيَّدة إلى [0,100].
     */
    private function discountPercentFor(Book $book): string
    {
        // publisher() له withDefault فلا يكون null؛ الدار الافتراضية بلا نسبة → null.
        $raw = $book->publisher?->cost_discount_percent;

        $discount = $raw !== null
            ? (string) $raw
            : (string) config('finance.default_cost_discount_percent', 25);

        // تقييد دفاعي: لا نسبة سالبة (تكلفة تفوق السعر) ولا > ١٠٠ (تكلفة سالبة).
        if (bccomp($discount, '0', 4) < 0) {
            $discount = '0';
        } elseif (bccomp($discount, '100', 4) > 0) {
            $discount = '100';
        }

        return $discount;
    }
}
