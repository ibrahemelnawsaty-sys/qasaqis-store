<?php

declare(strict_types=1);

namespace App\Actions\Category;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * يوائم جدول ترتيب كتب القسم (category_book_positions) مع عضويّة القسم الفعليّة:
 * الكتب التي قسمها الرئيسيّ هذا (books.category_id) أو أحد أقسامها الإضافيّة
 * (book_category). يُنفَّذ عند فتح القسم في الأدمن، فيضمن أن قائمة السحب ومصدر ترتيب
 * صفحة القسم في المتجر يعرضان كل كتب القسم الحاليّة لا غير — بلا تكرار ولا نواقص.
 *
 * الجديد يُلحَق في نهاية الترتيب (بترتيب الأحدث، مطابقًا للترتيب الافتراضي بالمتجر)،
 * والصفوف اليتيمة (كتاب لم يعد في القسم) تُحذف. لا يمسّ العضويّة نفسها — طبقة ترتيب فقط.
 */
final class SyncCategoryBookOrder
{
    public function execute(Category $category): void
    {
        // كل كتب القسم (الرئيسي أو الإضافي)، بترتيب الأحدث لتثبيت ترتيب أوّليّ متوقّع.
        $memberIds = Book::query()
            ->where(function (Builder $q) use ($category): void {
                $q->where('category_id', $category->id)
                    ->orWhereHas('categories', fn (Builder $c) => $c->whereKey($category->id));
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->pluck('id');

        // book_id => position الحاليّة.
        $existing = DB::table('category_book_positions')
            ->where('category_id', $category->id)
            ->pluck('position', 'book_id');

        $stale = $existing->keys()->map(static fn ($id): int => (int) $id)->diff($memberIds);
        $missing = $memberIds->reject(fn ($id): bool => $existing->has($id))->values();

        if ($stale->isEmpty() && $missing->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($category, $stale, $missing): void {
            if ($stale->isNotEmpty()) {
                DB::table('category_book_positions')
                    ->where('category_id', $category->id)
                    ->whereIn('book_id', $stale->all())
                    ->delete();
            }

            if ($missing->isEmpty()) {
                return;
            }

            // ألحِق الجديد بعد أكبر موضع حاليّ (تصاعديّ = الأعلى ظهورًا).
            $max = (int) DB::table('category_book_positions')
                ->where('category_id', $category->id)
                ->max('position');

            $rows = $missing->map(static fn ($bookId, int $i): array => [
                'category_id' => $category->id,
                'book_id' => (int) $bookId,
                'position' => $max + $i + 1,
            ])->all();

            // insertOrIgnore: آمن ضدّ التسابق (فتحان متزامنان للقسم عقب تغيير العضويّة)
            // فلا ينكسر بمفتاح مكرَّر — يحتفظ الأول بمواضعه ويتجاهل الثاني الصفوف الموجودة.
            DB::table('category_book_positions')->insertOrIgnore($rows);
        });
    }
}
