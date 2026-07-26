<?php

declare(strict_types=1);

namespace App\Observers;

use App\Filament\Pages\OpsDashboard;
use App\Models\Book;
use Illuminate\Support\Str;

/**
 * Keeps a book's Arabic search columns in sync on every write.
 *
 * Closes a documented gap: books created/edited from the Filament admin must be
 * indexed for the storefront search engine (constitution 0.9). Without this,
 * `title_normalized` (prefix autocomplete) and `search_index` (the unified
 * normalized FULLTEXT blob) would only be populated when the title mutator
 * happened to fire, missing publisher/category changes and admin-side edits.
 */
class BookObserver
{
    /** بادئة الرمز التلقائي — يميّزه عن رموز المورّدين (مثل «000102» من moon-edu). */
    private const SKU_PREFIX = 'QSQ-';

    /**
     * توليد رمز منتج (SKU) فريد تلقائيًّا للكتب الجديدة التي تُحفَظ بلا رمز — فلا
     * يُدخله الأدمن يدويًّا ولا يتكرّر. يُطلَق على الإنشاء فقط؛ رمز الكتاب القائم لا
     * يتغيّر. من أدخل رمزًا يدويًّا يُحترَم (يُتحقّق تفرّده في النموذج/القاعدة).
     */
    public function creating(Book $book): void
    {
        if (blank($book->sku)) {
            $book->sku = $this->generateUniqueSku();
        }
    }

    /**
     * Runs before both insert and update, so the normalized columns are written
     * inside the same query as the rest of the row — no extra save, no drift.
     */
    public function saving(Book $book): void
    {
        // Refresh the prefix-autocomplete column even when the title was set
        // without passing through the model's mutator (forceFill, copy, import).
        $book->title_normalized = Book::normalizeArabic((string) $book->title);

        // The unified blob needs publisher & category names; make sure both
        // relations are available before refreshSearchIndex() reads them. Only
        // queries the ones not already loaded (publisher has a withDefault()).
        $book->loadMissing(['publisher', 'category']);

        // Rebuild search_index from the current field + relation values.
        $book->refreshSearchIndex();
    }

    /**
     * إبطال كاش لوحة العمليات عند حفظ/حذف كتاب — بطاقتا «تغطية المخزون» و«الأكثر
     * مبيعًا» تُشتقّان من الكتب، فتظهر تغييرات المخزون فورًا لا بعد انتهاء المهلة.
     */
    public function saved(Book $book): void
    {
        OpsDashboard::flushDashboardCache();
    }

    public function deleted(Book $book): void
    {
        OpsDashboard::flushDashboardCache();
    }

    /**
     * رمز فريد: بادئة + 6 محارف عشوائية آمنة، مع إعادة المحاولة عند التصادم النادر
     * (withTrashed كي لا يصطدم برمز محذوف ناعمًا يحمله فهرس التفرّد). قيد UNIQUE في
     * القاعدة هو الضمان النهائي حتى تحت التزامن.
     */
    private function generateUniqueSku(): string
    {
        do {
            $sku = self::SKU_PREFIX.Str::upper(Str::random(6));
        } while (Book::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }
}
