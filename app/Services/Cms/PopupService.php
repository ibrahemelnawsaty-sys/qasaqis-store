<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\Popup;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Resolves the single pop-up (promo / survey / newsletter / announcement) that
 * should be offered to the current storefront request — constitution 0.8 CMS.
 *
 * Server-side gating (authoritative): is_active + schedule window (starts_at /
 * ends_at) + page targeting (target_pages) against the current path. The lighter,
 * viewport/behaviour concerns — target_devices, display_trigger and
 * display_frequency (once / once_per_session / always) — are enforced client-side
 * in partials/popup.blade.php, where a viewport and localStorage/sessionStorage
 * are actually available.
 *
 * Performance (constitution 5.4): the tiny set of active pop-ups is cached briefly.
 * The time-dependent schedule and the request-dependent page match are applied in
 * PHP afterwards, so the cache stays request-agnostic and correct within its TTL.
 */
class PopupService
{
    /**
     * Cache key + TTL for the active pop-up candidate list. A short TTL keeps CMS
     * edits appearing quickly without a save-time hook (out of this task's scope).
     */
    private const CACHE_KEY = 'cms.popups.active';

    private const CACHE_TTL_SECONDS = 60;

    /**
     * The active pop-up to render for this request, or null when none qualifies.
     */
    public function forRequest(Request $request): ?Popup
    {
        $candidates = $this->activeCandidates();

        if ($candidates->isEmpty()) {
            return null;
        }

        $now = Carbon::now();
        $path = $this->currentPath($request);

        // Candidates are already ordered by priority desc, so first match wins.
        return $candidates->first(
            fn (Popup $popup): bool => $this->withinSchedule($popup, $now)
                && $this->matchesPath($popup, $path)
        );
    }

    /**
     * Active pop-ups ordered by priority (highest first), briefly cached.
     * Wrapped in rescue() so the layout still renders before the table exists.
     *
     * @return Collection<int, Popup>
     */
    private function activeCandidates(): Collection
    {
        return rescue(
            fn (): Collection => Cache::remember(
                self::CACHE_KEY,
                self::CACHE_TTL_SECONDS,
                fn (): Collection => Popup::query()
                    ->where('is_active', true)
                    ->orderByDesc('priority')
                    ->orderByDesc('id')
                    ->get()
            ),
            new Collection,
            report: false,
        );
    }

    /**
     * Normalised current path with a single leading slash: '/', '/books',
     * '/books/the-slug'. Keeps pattern matching consistent regardless of how the
     * admin typed the target (with or without a leading slash).
     */
    private function currentPath(Request $request): string
    {
        return '/'.trim($request->path(), '/');
    }

    private function withinSchedule(Popup $popup, CarbonInterface $now): bool
    {
        if ($popup->starts_at !== null && $popup->starts_at->greaterThan($now)) {
            return false;
        }

        if ($popup->ends_at !== null && $popup->ends_at->lessThan($now)) {
            return false;
        }

        return true;
    }

    /**
     * True when the pop-up targets the current path. Empty target_pages means
     * "every page". Each target is matched with Str::is(), so '*' and suffix
     * wildcards like '/books/*' are supported.
     */
    private function matchesPath(Popup $popup, string $path): bool
    {
        $targets = $popup->target_pages;

        if (! is_array($targets) || $targets === []) {
            return true;
        }

        foreach ($targets as $target) {
            $pattern = trim((string) $target);

            if ($pattern === '') {
                continue;
            }

            if (Str::is('/'.trim($pattern, '/'), $path)) {
                return true;
            }
        }

        return false;
    }
}
