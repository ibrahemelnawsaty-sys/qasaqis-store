<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Category;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * بطاقة القسم (x-category-icon): تعرض الصورة المرفوعة من اللوحة (image_path) أولًا،
 * وإلا الأيقونة الاحتياطية. كان image_path يُتجاهَل فلا تظهر صورة الأدمن — هذا الإصلاح.
 *
 * تصيير مكوّن مباشر بلا قاعدة بيانات (Category غير محفوظ).
 */
final class CategoryIconTest extends TestCase
{
    public function test_it_renders_the_uploaded_image_when_image_path_is_set(): void
    {
        $cat = new Category([
            'slug' => 'كتب-تربوية',
            'image_path' => 'categories/edu-cover.jpg',
            'color_hex' => '#12B3A6',
        ]);

        $html = Blade::render('<x-category-icon :cat="$cat" />', ['cat' => $cat]);

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('categories/edu-cover.jpg', $html);
    }

    public function test_it_falls_back_to_the_svg_icon_without_an_image(): void
    {
        $cat = new Category(['slug' => 'no-image-here', 'color_hex' => '#12B3A6']);

        $html = Blade::render('<x-category-icon :cat="$cat" />', ['cat' => $cat]);

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringNotContainsString('<img', $html);
    }
}
