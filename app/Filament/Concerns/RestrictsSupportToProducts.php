<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\Book;
use App\Models\User;

/**
 * SERVER-SIDE scope for the «support» role (constitution 4.4 / anti-pattern 30):
 * a scoped support user may only see and act on engagement (reviews, inquiries)
 * tied to the books they are explicitly assigned — never all products.
 *
 * The real scope source is the `support_user_products` table (SupportUserProduct):
 * a row with a book_id grants one book, a row with a category_id grants a whole
 * category. This project does NOT use the docs' hypothetical `allowed_product_ids`
 * JSON column — it does not exist on `users` (verified against the migration), so
 * the actual pivot table is used instead (documented deviation, constitution 1.1).
 *
 * A Resource mixes this in and calls the helpers from getEloquentQuery() and its
 * per-record authorization overrides.
 */
trait RestrictsSupportToProducts
{
    /**
     * Whether the given user must be scoped to specific products.
     *
     * Only pure «support» users are scoped. Roles that legitimately see every
     * product's engagement (super_admin, admin, content_editor per docs/04 §2.2)
     * are never restricted, even if they also happen to hold «support».
     */
    protected static function isSupportScoped(?User $user): bool
    {
        return $user !== null
            && $user->hasRole('support')
            && ! $user->hasAnyRole(['super_admin', 'admin', 'content_editor']);
    }

    /**
     * The book ids a scoped support user may act on: direct book rows PLUS every
     * book inside an allowed category. Returns [] when the user has no scope rows
     * (which the callers translate into "match nothing").
     *
     * @return array<int, int>
     */
    protected static function supportAllowedBookIds(User $user): array
    {
        $rows = $user->supportProducts()->get(['book_id', 'category_id']);

        $bookIds = $rows->pluck('book_id')->filter()->all();
        $categoryIds = $rows->pluck('category_id')->filter()->all();

        if ($categoryIds !== []) {
            $bookIds = array_merge(
                $bookIds,
                Book::query()->whereIn('category_id', $categoryIds)->pluck('id')->all(),
            );
        }

        return array_values(array_unique(array_map('intval', $bookIds)));
    }

    /**
     * True when the current user may act on a record attached to $bookId.
     * Non-scoped users always pass; scoped support users pass only when the book
     * is inside their assigned scope (a null book is never in a support scope).
     */
    protected static function bookInSupportScope(?int $bookId): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! static::isSupportScoped($user)) {
            return true;
        }

        if ($bookId === null) {
            return false;
        }

        return in_array((int) $bookId, static::supportAllowedBookIds($user), true);
    }
}
