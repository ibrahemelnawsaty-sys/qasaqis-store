<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Validation for the search results page and the lightweight suggest endpoint.
 *
 * The search page reuses the exact same facet/sort whitelist as the catalogue
 * (a search can also be filtered/sorted), so this extends CatalogFilterRequest
 * and keeps the single source of truth for those rules. `q` is already covered
 * there (nullable|string|max:100).
 */
class SearchRequest extends CatalogFilterRequest
{
}
