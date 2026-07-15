<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Baseline navigation menus (constitution 0.8): a header and a footer menu, each
 * with starter items, so the Menus resource is populated and editable instead of
 * empty. Depends on PageSeeder — page links resolve via the polymorphic linkable
 * (link_type=page) so they keep working even if a page's slug changes later.
 *
 * Idempotent: menus keyed on the unique `location`; items keyed on
 * (menu_id + label). Re-running refreshes the baseline without duplicating.
 * Route-based items store an absolute URL (link_type=url); page items store a
 * polymorphic reference to the Page model, matching the footer's resolveMenuUrl.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $pages = Page::query()
            ->whereIn('slug', ['about', 'shipping-policy', 'returns-policy', 'faq', 'privacy-policy'])
            ->get()
            ->keyBy('slug');

        // ---- Header menu: main storefront entry points ----------------------
        $header = Menu::updateOrCreate(
            ['location' => 'header'],
            ['name' => 'القائمة الرئيسية', 'is_active' => true],
        );

        $this->urlItem($header, 'كل الكتب', route('books.index'), 1);
        $this->urlItem($header, 'العروض', route('books.offers'), 2);
        $this->pageItem($header, 'من نحن', $pages->get('about'), 3);

        // ---- Footer menu: informational / policy pages ----------------------
        $footer = Menu::updateOrCreate(
            ['location' => 'footer'],
            ['name' => 'قائمة الفوتر', 'is_active' => true],
        );

        $this->pageItem($footer, 'من نحن', $pages->get('about'), 1);
        $this->pageItem($footer, 'سياسة الشحن', $pages->get('shipping-policy'), 2);
        $this->pageItem($footer, 'الاستبدال والاسترجاع', $pages->get('returns-policy'), 3);
        $this->pageItem($footer, 'الأسئلة الشائعة', $pages->get('faq'), 4);
        $this->pageItem($footer, 'سياسة الخصوصية', $pages->get('privacy-policy'), 5);
    }

    /**
     * A direct-URL menu item (link_type=url).
     */
    private function urlItem(Menu $menu, string $label, string $url, int $sort): void
    {
        MenuItem::updateOrCreate(
            ['menu_id' => $menu->id, 'label' => $label],
            [
                'parent_id' => null,
                'url' => $url,
                'link_type' => 'url',
                'linkable_type' => null,
                'linkable_id' => null,
                'target' => '_self',
                'sort_order' => $sort,
                'is_active' => true,
            ],
        );
    }

    /**
     * A page-linked menu item (link_type=page, polymorphic). Skipped silently if
     * the page is missing so the seeder never invents a broken link.
     */
    private function pageItem(Menu $menu, string $label, ?Page $page, int $sort): void
    {
        if ($page === null) {
            return;
        }

        MenuItem::updateOrCreate(
            ['menu_id' => $menu->id, 'label' => $label],
            [
                'parent_id' => null,
                'url' => null,
                'link_type' => 'page',
                'linkable_type' => Page::class,
                'linkable_id' => $page->id,
                'target' => '_self',
                'sort_order' => $sort,
                'is_active' => true,
            ],
        );
    }
}
