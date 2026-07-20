<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Book;
use App\Models\Review;

/**
 * يعيد حساب books.avg_rating و books.reviews_count من المراجعات المنشورة.
 *
 * العمودان موجودان في السكيمة منذ create_books_table لكن **لا كود يكتبهما** —
 * كانا راكدَين على 0 مهما اعتُمدت المراجعات. هذا المراقب يجعلهما مشتقّين من
 * جدول reviews وحده: مصدر الحقيقة هو الصفوف، والعمودان مجرد لقطة محسوبة.
 *
 * ملاحظة تسجيل (بند 1.5): هذا المراقب **غير مسجَّل** في AppServiceProvider —
 * الملف خارج ملكية هذا الوكيل. على المنسّق إضافة
 * `Review::observe(ReviewObserver::class);` في boot() وإلا بقي كودًا ميتًا.
 */
class ReviewObserver
{
    /** الحالة الوحيدة التي تدخل في المتوسط (من enum عمود reviews.status). */
    private const PUBLISHED = 'published';

    public function saved(Review $review): void
    {
        $this->recalculate((int) $review->book_id);

        // نقل مراجعة من كتاب إلى آخر من لوحة الأدمن يترك الكتاب الأصلي بمتوسط
        // قديم. getOriginal يعيد null عند الإنشاء فيُتخطّى الفرع تلقائيًا.
        $previousBookId = $review->getOriginal('book_id');

        if ($previousBookId !== null && (int) $previousBookId !== (int) $review->book_id) {
            $this->recalculate((int) $previousBookId);
        }
    }

    public function deleted(Review $review): void
    {
        $this->recalculate((int) $review->book_id);
    }

    /**
     * يُعاد الحساب عند كل حفظ بلا شروط مسبقة على الحالة: الفرق بضعة استعلامات
     * مفهرسة على مسار كتابة نادر (إرسال رأي أو إجراء إشراف)، مقابل خطر أن يفوت
     * شرطٌ ذكيٌّ حالةً — تعديل rating لمراجعة منشورة، أو إخفاءها — فيبقى الرقم كاذبًا.
     */
    private function recalculate(int $bookId): void
    {
        if ($bookId <= 0) {
            return;
        }

        // الفهرس المركّب ['book_id','status'] يخدم هذا الشرط مباشرةً.
        $published = Review::query()
            ->where('book_id', $bookId)
            ->where('status', self::PUBLISHED)
            ->whereNull('parent_id')   // ردود الطاقم ليست مراجعات.
            ->whereNotNull('rating');  // صفوف بلا تقييم لا تدخل المتوسط.

        $count = (clone $published)->count();
        $average = $count > 0 ? (float) (clone $published)->avg('rating') : 0.0;

        // تحديث بالاستعلام لا بحفظ الموديل: BookObserver::saving يعيد بناء فهرس
        // البحث العربي ويحمّل علاقتَي الناشر والقسم عند كل حفظ — كلفة بلا مقابل
        // لتحديث رقمين مشتقّين، ولا يمكن أن يغيّرهما المراقب فيتولّد تكرار لانهائي.
        // withTrashed كي لا يفقد كتاب محذوف ناعمًا أرقامه الصحيحة عند استرجاعه.
        Book::query()
            ->withTrashed()
            ->whereKey($bookId)
            ->update([
                'reviews_count' => $count,
                // avg_rating عمود DECIMAL(2,1) — خانة عشرية واحدة لا أكثر.
                'avg_rating' => round($average, 1),
            ]);
    }
}
