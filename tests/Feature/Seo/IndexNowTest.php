<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * IndexNow: ملف المفتاح + الإبلاغ الفوري عند نشر/تعديل محتوى (Bing/Yandex).
 * مُعطّل ما لم يُضبط المفتاح. (الطابور sync في الاختبار فيُنفَّذ الإبلاغ فورًا، وHttp
 * مزيّف فلا اتصال فعليّ.)
 */
class IndexNowTest extends TestCase
{
    use RefreshDatabase;

    public function test_key_file_serves_the_configured_key_only(): void
    {
        config(['seo.indexnow_key' => 'abc123def456']);
        $this->get('/abc123def456.txt')->assertOk()->assertSee('abc123def456');

        // مفتاح خاطئ.
        $this->get('/wrongkey9999.txt')->assertNotFound();

        // معطّل (لا مفتاح).
        config(['seo.indexnow_key' => '']);
        $this->get('/abc123def456.txt')->assertNotFound();
    }

    public function test_publishing_a_book_pings_indexnow(): void
    {
        Http::fake();
        config(['seo.indexnow_key' => 'abc123def456']);
        $cat = Category::factory()->create();

        $book = Book::factory()->create(['category_id' => $cat->id, 'is_published' => true]);

        Http::assertSent(function ($request) use ($book): bool {
            return str_contains($request->url(), 'indexnow')
                && in_array(rtrim(config('seo.site_url'), '/') . '/books/' . $book->slug, $request['urlList'] ?? [], true);
        });
    }

    public function test_unpublished_book_does_not_ping(): void
    {
        Http::fake();
        config(['seo.indexnow_key' => 'abc123def456']);
        $cat = Category::factory()->create();

        Book::factory()->create(['category_id' => $cat->id, 'is_published' => false]);

        Http::assertNothingSent();
    }

    public function test_disabled_when_no_key_is_set(): void
    {
        Http::fake();
        config(['seo.indexnow_key' => '']);
        $cat = Category::factory()->create();

        Book::factory()->create(['category_id' => $cat->id, 'is_published' => true]);

        Http::assertNothingSent();
    }
}
