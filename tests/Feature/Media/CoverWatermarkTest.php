<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * حماية الصور: الأغلفة تُخدَم عبر مسار بالمعرّف (/media/book/{slug}) موسومةً بالعلامة
 * المائية — فلا يُكشف اسم الملف الأصلي في /storage ولا يُوصَل للأصل النظيف بتعديل الرابط.
 */
final class CoverWatermarkTest extends TestCase
{
    use RefreshDatabase;

    private function book(array $attrs = []): Book
    {
        return Book::factory()->create($attrs + ['category_id' => Category::factory()->create()->id]);
    }

    public function test_cover_url_uses_media_route_for_storage_paths(): void
    {
        $book = $this->book(['cover_image' => 'books/covers/x.jpg']);

        // لا يكشف المسار: يستخدم route بالمعرّف لا /storage.
        $this->assertSame(route('media.book', $book), $book->coverUrl());
        $this->assertStringNotContainsString('/storage/', (string) $book->coverUrl());
    }

    public function test_cover_url_passes_through_external_static_and_null(): void
    {
        $this->assertSame('https://cdn.example/x.jpg', $this->book(['cover_image' => 'https://cdn.example/x.jpg'])->coverUrl());
        $this->assertStringContainsString('images/logo.png', (string) $this->book(['cover_image' => 'images/logo.png'])->coverUrl());
        $this->assertNull($this->book(['cover_image' => null])->coverUrl());
    }

    public function test_media_route_serves_a_watermarked_jpeg(): void
    {
        Storage::fake('public');

        // صورة حقيقية في مسار الغلاف كي يعالجها GD.
        $img = imagecreatetruecolor(240, 320);
        imagefill($img, 0, 0, imagecolorallocate($img, 210, 160, 120));
        ob_start();
        imagejpeg($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);
        Storage::disk('public')->put('books/covers/wm-test.jpg', $bytes);

        $book = $this->book(['cover_image' => 'books/covers/wm-test.jpg']);

        $response = $this->get(route('media.book', $book));
        $response->assertOk();
        $this->assertStringContainsString('image/jpeg', (string) $response->headers->get('Content-Type'));
        // بايتات صورة فعلية (مخرَج JPEG صالح من المعالجة).
        $this->assertNotFalse(@getimagesizefromstring($this->fileResponseBytes($response)));
    }

    private function fileResponseBytes(\Illuminate\Testing\TestResponse $response): string
    {
        $r = $response->baseResponse;

        if ($r instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            return (string) file_get_contents($r->getFile()->getPathname());
        }

        return (string) $r->getContent();
    }

    public function test_media_route_404_when_file_missing(): void
    {
        Storage::fake('public');
        $book = $this->book(['cover_image' => 'books/covers/nope.jpg']);

        $this->get(route('media.book', $book))->assertNotFound();
    }
}
