<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * حماية الصور + أداؤها: الأغلفة تُخدَم كمشتقّات موسومة **ثابتة** من public/media-cache
 * (يخدمها خادم الويب بلا PHP). مسار /media/book/{slug} احتياطيّ يولّد المشتقّ عند أوّل
 * طلب ثم تعيد coverUrl() رابطه الثابت. لا يُكشف اسم الملف الأصلي في /storage.
 *
 * معزول عن نظام الملفات: usePublicPath يوجّه الكتابة إلى مجلّد مؤقّت لا public/ الحقيقي.
 */
final class CoverWatermarkTest extends TestCase
{
    use RefreshDatabase;

    private string $tempPublic;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // عزل: المشتقّات تُكتب هنا لا في public/ الحقيقي للمستودع.
        $this->tempPublic = storage_path('framework/testing/pub-'.bin2hex(random_bytes(6)));
        @mkdir($this->tempPublic, 0755, true);
        $this->app->usePublicPath($this->tempPublic);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempPublic)) {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tempPublic, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($items as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
            @rmdir($this->tempPublic);
        }

        parent::tearDown();
    }

    private function book(array $attrs = []): Book
    {
        return Book::factory()->create($attrs + ['category_id' => Category::factory()->create()->id]);
    }

    /** يضع صورة JPEG حقيقية في مسار الغلاف كي يعالجها GD. */
    private function putRealCover(string $path): void
    {
        $img = imagecreatetruecolor(240, 320);
        imagefill($img, 0, 0, imagecolorallocate($img, 210, 160, 120));
        ob_start();
        imagejpeg($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);
        Storage::disk('public')->put($path, $bytes);
    }

    public function test_cover_url_falls_back_to_media_route_when_derivative_missing(): void
    {
        $book = $this->book(['cover_image' => 'books/covers/x.jpg']);

        // لا مشتقّ ثابت بعد => مسار بالمعرّف (لا /storage) يولّده عند أوّل طلب.
        $this->assertSame(route('media.book', $book), $book->coverUrl());
        $this->assertStringNotContainsString('/storage/', (string) $book->coverUrl());
    }

    public function test_cover_url_passes_through_external_static_and_null(): void
    {
        $this->assertSame('https://cdn.example/x.jpg', $this->book(['cover_image' => 'https://cdn.example/x.jpg'])->coverUrl());
        $this->assertStringContainsString('images/logo.png', (string) $this->book(['cover_image' => 'images/logo.png'])->coverUrl());
        $this->assertNull($this->book(['cover_image' => null])->coverUrl());
    }

    public function test_media_route_serves_a_watermarked_derivative_and_switches_coverurl(): void
    {
        $this->putRealCover('books/covers/wm-test.jpg');
        $book = $this->book(['cover_image' => 'books/covers/wm-test.jpg']);

        $response = $this->get(route('media.book', $book));
        $response->assertOk();
        // WebP إن توفّر imagewebp (الدستور 5.3)، وإلا JPEG — النوع يتبع MediaCache::ext.
        $expectedMime = \App\Services\Media\MediaCache::ext() === 'webp' ? 'image/webp' : 'image/jpeg';
        $this->assertStringContainsString($expectedMime, (string) $response->headers->get('Content-Type'));
        $this->assertNotFalse(@getimagesizefromstring($this->fileResponseBytes($response)));

        // المشتقّ الثابت العام وُلِّد => coverUrl صارت static (بلا PHP) في الطلب التالي.
        $book->refresh();
        $this->assertStringContainsString('media-cache/', (string) $book->coverUrl());
        $this->assertNotSame(route('media.book', $book), $book->coverUrl());
    }

    public function test_media_route_carries_no_session_or_csrf_cookie(): void
    {
        // بلا جلسة/CSRF (rank 3): يُسقِط Set-Cookie فيخزّنها الـ CDN على الحافة.
        $this->putRealCover('books/covers/nocookie.jpg');
        $book = $this->book(['cover_image' => 'books/covers/nocookie.jpg']);

        $response = $this->get(route('media.book', $book));
        $response->assertOk();

        $cookieNames = array_map(static fn ($c): string => $c->getName(), $response->headers->getCookies());
        $this->assertNotContains(config('session.cookie'), $cookieNames);
        $this->assertNotContains('XSRF-TOKEN', $cookieNames);
    }

    public function test_media_warm_command_pregenerates_static_derivative(): void
    {
        $this->putRealCover('books/covers/warm-me.jpg');
        $book = $this->book(['cover_image' => 'books/covers/warm-me.jpg']);

        $this->assertSame(route('media.book', $book), $book->coverUrl());

        $this->artisan('media:warm')->assertSuccessful();

        // بعد الإحماء: المشتقّ موجود => coverUrl static بلا أوّل طلب على PHP.
        $this->assertStringContainsString('media-cache/', (string) $book->fresh()->coverUrl());
    }

    public function test_media_warm_skips_a_missing_source_without_failing(): void
    {
        // غلاف مساره مضبوط لكن بلا ملفٍّ على القرص (لم يُرفَع بعد، أو BOOK10) — يُتخطّى
        // لا يُعدّ فشلًا، فلا يُفشِل النشر ولا يُلبِس الرسالة سببًا خاطئًا (صلاحية الكتابة).
        $this->book(['cover_image' => 'books/covers/never-uploaded.jpg']);

        $this->artisan('media:warm')->assertSuccessful();
    }

    public function test_media_route_404_when_file_missing(): void
    {
        $book = $this->book(['cover_image' => 'books/covers/nope.jpg']);

        $this->get(route('media.book', $book))->assertNotFound();
    }

    public function test_admin_thumb_route_serves_a_small_derivative_and_switches_admin_thumb_url(): void
    {
        $this->putRealCover('books/covers/thumb-test.jpg');
        $book = $this->book(['cover_image' => 'books/covers/thumb-test.jpg']);

        // قبل التوليد: adminThumbUrl تشير لمسار التوليد (لا static، لا /storage).
        $this->assertSame(route('media.book-thumb', $book), $book->adminThumbUrl());

        $response = $this->get(route('media.book-thumb', $book));
        $response->assertOk();
        $expectedMime = \App\Services\Media\MediaCache::ext() === 'webp' ? 'image/webp' : 'image/jpeg';
        $this->assertStringContainsString($expectedMime, (string) $response->headers->get('Content-Type'));

        // المصغّر صغير فعلًا: أقصى ضلع ≤ THUMB_MAX (الأصل 240×320 يُصغَّر).
        $size = getimagesizefromstring($this->fileResponseBytes($response));
        $this->assertNotFalse($size);
        $this->assertLessThanOrEqual(\App\Services\Media\MediaCache::THUMB_MAX, max((int) $size[0], (int) $size[1]));

        // بعد التوليد: adminThumbUrl صارت static (media-cache/thumbs) بلا PHP.
        $book->refresh();
        $this->assertStringContainsString('media-cache/thumbs/', (string) $book->adminThumbUrl());
    }

    private function fileResponseBytes(\Illuminate\Testing\TestResponse $response): string
    {
        $r = $response->baseResponse;

        if ($r instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            return (string) file_get_contents($r->getFile()->getPathname());
        }

        return (string) $r->getContent();
    }
}
