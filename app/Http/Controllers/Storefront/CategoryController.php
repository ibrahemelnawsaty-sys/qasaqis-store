<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\FiltersBooks;
use App\Http\Requests\CatalogFilterRequest;
use App\Models\Category;
use Illuminate\Contracts\View\View;

class CategoryController extends Controller
{
    use FiltersBooks;

    public function show(CatalogFilterRequest $request, Category $category): View
    {
        abort_unless($category->is_active, 404);

        return view('catalog.index', [
            'books' => $this->filteredBooks($request, $category),
            'category' => $category,
            'heading' => $category->name,
            'searchTerm' => null,
            'categories' => $this->categoriesWithCounts(),
            'publishers' => $this->publishersWithCounts(),
            'ageOptions' => $this->ageOptions(),
        ]);
    }
}
