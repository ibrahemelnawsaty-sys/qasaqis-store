<?php

declare(strict_types=1);

namespace App\Actions\Category;

use Illuminate\Support\Facades\DB;

/**
 * ينقل كتابًا إلى رتبة محدّدة داخل قسم ثم يعيد ترقيم كل كتب القسم تسلسليًّا (1..N)،
 * فلا تتعارض الأرقام أبدًا: يكتب الأدمن رقمًا لكتابٍ فيُدرَج في تلك الرتبة ويُزاح الباقي
 * تلقائيًّا (كالسحب لكن بالكتابة). يعتمد على category_book_positions (طبقة ترتيب القسم).
 */
final class MoveCategoryBookToPosition
{
    public function execute(int $categoryId, int $bookId, int $target): void
    {
        // ترتيب معرّفات كتب القسم الحاليّ (position ثم book_id لكسر التعادل بثبات).
        $ids = DB::table('category_book_positions')
            ->where('category_id', $categoryId)
            ->orderBy('position')
            ->orderBy('book_id')
            ->pluck('book_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        // انزع الكتاب من موضعه الحالي ثم أدرِجه في الرتبة المطلوبة (محصورة ضمن [1، العدد]).
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id !== $bookId));
        $target = max(1, min($target, count($ids) + 1));
        array_splice($ids, $target - 1, 0, [$bookId]);

        // أعِد الترقيم تسلسليًّا 1..N في معاملة واحدة (أرقام فريدة بلا فجوات ولا تكرار).
        DB::transaction(function () use ($ids, $categoryId): void {
            foreach ($ids as $index => $id) {
                DB::table('category_book_positions')
                    ->where('category_id', $categoryId)
                    ->where('book_id', $id)
                    ->update(['position' => $index + 1]);
            }
        });
    }
}
