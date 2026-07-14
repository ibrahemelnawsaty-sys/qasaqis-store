<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\BookResource;
use App\Filament\Resources\CouponResource;
use App\Filament\Resources\ReviewResource;
use App\Models\Book;
use App\Models\Review;
use App\Models\SupportUserProduct;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Filament access control is enforced SERVER-SIDE (constitution 4.4 / anti-pattern
 * 13): the Resource authorization hooks check the atomic permission via the Gate —
 * never just hiding a button. The «support» role is additionally scoped to its
 * assigned products (anti-pattern 30).
 *
 * These call the Resources' static authorization methods directly, which is
 * exactly what Filament invokes; auth()->user() is set via actingAs().
 *
 * HONESTY (1.3/1.5): NOT executed here (no PHP); runs via `php artisan test`.
 */
final class FilamentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_user_without_permission_cannot_view_a_resource(): void
    {
        $user = User::factory()->create(); // no roles, no permissions.

        $this->actingAs($user);

        $this->assertFalse(BookResource::canViewAny());
        $this->assertFalse(BookResource::canCreate());
        $this->assertFalse(CouponResource::canViewAny());
    }

    public function test_a_single_permission_grants_only_its_own_resource(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('coupons.view');

        $this->actingAs($user);

        // Holds coupons.view => can view coupons, but NOT books (products.view).
        $this->assertTrue(CouponResource::canViewAny());
        $this->assertFalse(BookResource::canViewAny());
    }

    public function test_view_permission_does_not_imply_create_or_delete(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('products.view'); // books use the «products» prefix.

        $this->actingAs($user);

        $book = Book::factory()->create();

        $this->assertTrue(BookResource::canViewAny());
        $this->assertFalse(BookResource::canCreate());   // needs products.create
        $this->assertFalse(BookResource::canDelete($book)); // needs products.delete
    }

    public function test_super_admin_bypasses_every_check(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $this->actingAs($user);

        $book = Book::factory()->create();

        // Gate::before grants super_admin everything without explicit permissions.
        $this->assertTrue(BookResource::canViewAny());
        $this->assertTrue(BookResource::canCreate());
        $this->assertTrue(BookResource::canDelete($book));
        $this->assertTrue(CouponResource::canViewAny());
    }

    public function test_support_user_is_scoped_to_assigned_products_only(): void
    {
        $bookA = Book::factory()->create();
        $bookB = Book::factory()->create();

        $reviewA = $this->publishedReview($bookA);
        $reviewB = $this->publishedReview($bookB);

        $support = User::factory()->create();
        $support->assignRole('support'); // has reviews.view but is product-scoped.
        // Assigned to bookA only.
        SupportUserProduct::create(['user_id' => $support->id, 'book_id' => $bookA->id]);

        $this->actingAs($support);

        // Can act on the assigned book's review, but not the other book's.
        $this->assertTrue(ReviewResource::canView($reviewA));
        $this->assertFalse(ReviewResource::canView($reviewB));

        // The list query only returns reviews inside the support scope.
        $listed = ReviewResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($reviewA->id, $listed);
        $this->assertNotContains($reviewB->id, $listed);
    }

    public function test_support_user_with_no_scope_rows_sees_nothing(): void
    {
        $book = Book::factory()->create();
        $this->publishedReview($book);

        $support = User::factory()->create();
        $support->assignRole('support'); // no SupportUserProduct rows at all.

        $this->actingAs($support);

        $this->assertSame([], ReviewResource::getEloquentQuery()->pluck('id')->all());
    }

    private function publishedReview(Book $book): Review
    {
        return Review::create([
            'book_id' => $book->id,
            'parent_id' => null,
            'author_name' => 'أم محمد',
            'rating' => 5,
            'title' => 'كتاب رائع',
            'body' => 'استمتع به طفلي كثيرًا.',
            'status' => 'published',
        ]);
    }
}
