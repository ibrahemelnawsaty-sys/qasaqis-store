<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewRequest;
use App\Models\Book;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * استقبال رأي عميلة مسجَّلة على كتاب.
 *
 * المراجعة تُحفظ بحالة `pending` دائمًا فتظهر في مورد المراجعات بلوحة الأدمن
 * للاعتماد اليدوي؛ لا مسار يجعل رأيًا منشورًا تلقائيًا.
 */
class ReviewController extends Controller
{
    /** حالات الطلب التي تُعدّ شراءً مكتملًا (من enum عمود orders.status). */
    private const FULFILLED_STATUSES = ['delivered', 'completed'];

    /** حالات المراجعة التي تُعدّ «رأيًا قائمًا» يمنع إرسال رأي ثانٍ. */
    private const ACTIVE_STATUSES = ['pending', 'published'];

    public function store(ReviewRequest $request, Book $book): RedirectResponse
    {
        // نفس سلوك BookController::show — الكتاب غير المنشور غير موجود.
        abort_unless($book->is_published, 404);

        // دفاع في العمق (بند 4.4): المسار محمي بحارس customer، ولا نكتفي بالوسيط.
        $customer = $request->user('customer');
        abort_if($customer === null, 403);

        $phone = (string) $customer->phone_normalized;

        if ($this->hasActiveReview((int) $book->id, $phone)) {
            return back()
                ->with('review_error', __('review.already_reviewed'))
                ->withFragment('pdp-reviews');
        }

        // معاملة واحدة لأن المراقب يكتب في جدول ثانٍ (books) ضمن نفس العملية (بند 3.5).
        DB::transaction(fn () => Review::create($request->toAttributes(
            bookId: (int) $book->id,
            authorName: (string) $customer->name,
            authorPhone: $phone,
            isVerifiedPurchase: $this->hasFulfilledPurchase((int) $customer->id, (int) $book->id),
        )));

        return back()
            ->with('review_success', true)
            ->withFragment('pdp-reviews');
    }

    /**
     * شارة «شراء موثّق» — تُحسب خادميًا حصريًا ولا تُقبل من العميل بأي حال (4.1).
     *
     * الأساس هو طلب **مربوط بحساب العميلة** (orders.customer_id)، لا مطابقة رقم
     * جوال: تسجيل الحساب لا يثبت ملكية الرقم (customers.phone_verified_at يبقى
     * NULL بقرار معماري)، فمطابقة الجوال كانت ستمنح من يسجّل برقم غيرها شارةَ
     * شرائها. هذا هو نفس مبدأ «الربط بدليل خاص بالطلب» المطبَّق في orders.claim.
     */
    private function hasFulfilledPurchase(int $customerId, int $bookId): bool
    {
        return Order::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', self::FULFILLED_STATUSES)
            ->whereHas('items', static fn (Builder $query): Builder => $query->where('book_id', $bookId))
            ->exists();
    }

    /**
     * منع إغراق كتاب واحد بآراء متكرّرة من نفس العميلة (بند 4.6 — الإساءة على
     * نماذج الإرسال العامة). المقارنة على phone_normalized لأن جدول reviews بلا
     * عمود customer_id. الحالتان hidden/spam لا تمنعان المحاولة من جديد.
     */
    private function hasActiveReview(int $bookId, string $authorPhone): bool
    {
        if ($authorPhone === '') {
            return false;
        }

        return Review::query()
            ->where('book_id', $bookId)
            ->where('author_phone', $authorPhone)
            ->whereNull('parent_id')
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->exists();
    }
}
