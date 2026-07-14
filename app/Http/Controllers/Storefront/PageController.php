<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    /**
     * Show a single CMS page resolved by its slug (implicit {page:slug} binding,
     * which already excludes soft-deleted rows). Only published pages are visible:
     * unpublished drafts return 404 so they never leak — mirrors the published
     * scope on the model (Page::scopePublished checks is_published). The SEO row
     * is eager-loaded to avoid an N+1 when the view reads meta fields.
     */
    public function show(Page $page): View
    {
        abort_unless($page->is_published, 404);

        $page->loadMissing('seo');

        return view('pages.show', ['page' => $page]);
    }
}
