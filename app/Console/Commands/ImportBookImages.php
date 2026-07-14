<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Book;
use App\Models\BookImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Imports the seeded book images into the PUBLIC disk and rewrites the stored
 * paths so the storefront can actually serve them.
 *
 * WHY this command exists:
 *  - BookSeeder stores the *source* path (e.g. "BOOK/BOOK1/WhatsApp ....jpeg")
 *    in both books.cover_image and book_images.path. Those paths point at files
 *    under database/seed/ which are NOT web-reachable, so covers render as a
 *    neutral placeholder until the real files are published.
 *  - This command copies each real file from the source folder to the public
 *    disk (storage/app/public/books/{slug}/...) and updates the DB rows with the
 *    new public path. It is idempotent: re-running never duplicates rows and, by
 *    default, does not re-copy files that are already published (use --force to
 *    overwrite).
 *
 * Honest handling of missing content (constitution 0.4 / 1.1 — nothing invented):
 *  - BOOK10 «هون عليك» has no images at all => the book is skipped with a warning,
 *    its cover_image stays NULL, and no image rows are fabricated.
 *  - A missing source root aborts with a clear message (never a silent success).
 *  - A missing per-book folder or per-file is reported and skipped, not guessed.
 *
 * Requires `php artisan storage:link` so storage/app/public is reachable at /storage.
 */
class ImportBookImages extends Command
{
    /**
     * --source : root folder that CONTAINS the per-book folders (BOOK1, BOOK2, ...).
     *            Defaults to database/seed/BOOK. The JSON "folder" field selects the
     *            sub-folder and the image's own basename selects the file.
     * --force  : overwrite files already present on the public disk.
     */
    protected $signature = 'books:import-images {--source= : Source root containing the BOOK* folders (default: database/seed/BOOK)} {--force : Overwrite images already copied to the public disk}';

    protected $description = 'Copy seeded book images to the public disk and update book_images + books.cover_image with the new public paths (idempotent).';

    private const PUBLIC_DISK = 'public';

    public function handle(): int
    {
        $jsonPath = database_path('seed/books.json');

        if (! File::exists($jsonPath)) {
            $this->error("books.json not found at {$jsonPath} — nothing to import.");

            return self::FAILURE;
        }

        // Source root that holds the per-book folders. --source overrides the default.
        $sourceRoot = rtrim((string) ($this->option('source') ?: base_path('database/seed/BOOK')), '/\\');

        if (! File::isDirectory($sourceRoot)) {
            // Fail loudly: the admin must place the original image folders here first.
            $this->error("Source folder not found: {$sourceRoot}");
            $this->line('Place the original per-book folders (BOOK1, BOOK2, ...) there, or pass --source=/absolute/path.');

            return self::FAILURE;
        }

        if (! $this->publicDiskLinked()) {
            // Not fatal (files still get copied), but the images will 404 until linked.
            $this->warn('Reminder: run `php artisan storage:link` so storage/app/public is served at /storage.');
        }

        try {
            /** @var array<int, array<string, mixed>> $books */
            $books = json_decode(File::get($jsonPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->error("books.json is not valid JSON: {$e->getMessage()}");

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');

        $stats = ['copied' => 0, 'skipped' => 0, 'missing' => 0, 'books' => 0, 'booksSkipped' => 0];

        foreach ($books as $data) {
            $this->importBook($data, $sourceRoot, $force, $stats);
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. Books updated: %d, skipped: %d | Files copied: %d, already present: %d, missing: %d.',
            $stats['books'],
            $stats['booksSkipped'],
            $stats['copied'],
            $stats['skipped'],
            $stats['missing'],
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $stats
     */
    private function importBook(array $data, string $sourceRoot, bool $force, array &$stats): void
    {
        $folder = trim((string) ($data['folder'] ?? ''));
        $slug = trim((string) ($data['slug_ar'] ?? ''));
        $cover = trim((string) ($data['cover_image'] ?? ''));
        /** @var array<int, string> $gallery */
        $gallery = array_values(array_filter(
            array_map(static fn ($p): string => trim((string) $p), (array) ($data['gallery_images'] ?? [])),
            static fn (string $p): bool => $p !== '',
        ));

        // BOOK10 and any other entry with no images at all: nothing to import.
        if ($cover === '' && $gallery === []) {
            $this->warn("[{$folder}] no images in seed data — skipped (cover stays placeholder).");
            $stats['booksSkipped']++;

            return;
        }

        // The image rows/cover belong to a seeded Book; match on the same key the
        // seeder used (slug = slug_ar). If the book was never seeded, skip honestly.
        $book = Book::query()->where('slug', $slug)->first();

        if (! $book) {
            $this->warn("[{$folder}] no Book found for slug '{$slug}' — run `php artisan db:seed` first. Skipped.");
            $stats['booksSkipped']++;

            return;
        }

        $bookSourceDir = $sourceRoot.DIRECTORY_SEPARATOR.$folder;

        if (! File::isDirectory($bookSourceDir)) {
            $this->warn("[{$folder}] source folder missing: {$bookSourceDir} — skipped.");
            $stats['booksSkipped']++;

            return;
        }

        // Plan the copy jobs first (cover + gallery), each with a deterministic,
        // web-safe destination name so re-runs overwrite the same target.
        $jobs = [];

        if ($cover !== '') {
            $jobs[] = [
                'collection' => 'cover',
                'is_cover' => true,
                'sort_order' => 0,
                'source' => $bookSourceDir.DIRECTORY_SEPARATOR.basename($cover),
                'dest' => "books/{$slug}/cover.".$this->extension($cover),
            ];
        }

        foreach ($gallery as $i => $path) {
            $n = $i + 1;
            $jobs[] = [
                'collection' => 'gallery',
                'is_cover' => false,
                'sort_order' => $n, // mirrors BookSeeder (gallery sort_order = i + 1).
                'source' => $bookSourceDir.DIRECTORY_SEPARATOR.basename($path),
                'dest' => "books/{$slug}/gallery-{$n}.".$this->extension($path),
            ];
        }

        // Copy the physical files (outside the DB transaction; filesystem I/O is
        // not transactional and we only want the DB to reflect successful copies).
        $done = [];

        foreach ($jobs as $job) {
            if (! File::isFile($job['source'])) {
                $this->warn("  [{$folder}] source file missing: {$job['source']} — skipped.");
                $stats['missing']++;

                continue;
            }

            $exists = Storage::disk(self::PUBLIC_DISK)->exists($job['dest']);

            if ($exists && ! $force) {
                $stats['skipped']++;
            } else {
                // File::get reads raw bytes; Storage::put writes them to the public
                // disk, creating books/{slug}/ as needed.
                Storage::disk(self::PUBLIC_DISK)->put($job['dest'], File::get($job['source']));
                $stats['copied']++;
            }

            $done[] = $job;
        }

        if ($done === []) {
            $this->warn("[{$folder}] no source files could be copied — DB left unchanged.");

            return;
        }

        // Persist the new public paths atomically (books + book_images together).
        DB::transaction(function () use ($book, $done): void {
            $coverPublicPath = null;

            foreach ($done as $job) {
                BookImage::updateOrCreate(
                    // Match the stable identity of the row, not the (changing) path,
                    // so re-imports update in place instead of duplicating.
                    [
                        'book_id' => $book->id,
                        'collection' => $job['collection'],
                        'sort_order' => $job['sort_order'],
                    ],
                    [
                        'path' => $job['dest'],
                        'disk' => self::PUBLIC_DISK,
                        'is_cover' => $job['is_cover'],
                        'alt' => $book->title,
                    ],
                );

                if ($job['is_cover']) {
                    $coverPublicPath = $job['dest'];
                }
            }

            // Point books.cover_image at the published cover (only if we imported one).
            if ($coverPublicPath !== null) {
                $book->forceFill(['cover_image' => $coverPublicPath])->save();
            }
        });

        $this->info("[{$folder}] {$slug}: ".count($done).' image(s) published.');
        $stats['books']++;
    }

    /**
     * Lower-cased file extension, defaulting to jpg when the source has none.
     */
    private function extension(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $ext !== '' ? $ext : 'jpg';
    }

    /**
     * Whether the public/storage symlink (or folder) exists so /storage resolves.
     */
    private function publicDiskLinked(): bool
    {
        return File::exists(public_path('storage'));
    }
}
