<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Book;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * ترتيب ظهور الكتب في صفحة العروض (/offers) — بالسحب أو بكتابة رقم. تعرض الكتب المخصومة
 * (لها old_price)، والترتيب يُكتب في books.offer_sort_order (مستقلّ عن sort_order لـ/books).
 *
 * الظهور في /offers مقرون بالخصم (وضع/إزالة old_price على الكتاب)، وهذه الشاشة للترتيب فقط.
 * عند الفتح تُلحَق الكتب المخصومة غير المرتَّبة بنهاية الترتيب (syncOfferPositions) فتظهر كلها.
 *
 * الصلاحية: العرض بـ products.view؛ السحب/الكتابة بـ products.update (كمورد الكتب).
 */
class OfferOrder extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-bars-arrow-down';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_BOOKS_CONTENT;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'ترتيب العروض';

    protected static ?string $title = 'ترتيب صفحة العروض';

    protected static string $view = 'filament.pages.offer-order';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('products.view');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        // ألحِق الكتب المخصومة غير المرتَّبة (offer_sort_order = 0) بنهاية الترتيب كي تظهر
        // كلها مرتّبة ويتّسق العرض مع صفحة العروض.
        Book::syncOfferPositions();
    }

    public function table(Table $table): Table
    {
        $canManage = (bool) auth()->user()?->can('products.update');

        return $table
            ->query(
                Book::query()
                    ->published()
                    ->whereNotNull('old_price')
                    ->select(['id', 'title', 'author', 'cover_image', 'price', 'old_price', 'offer_sort_order', 'published_at'])
            )
            ->defaultSort('offer_sort_order')
            // اسحب لإعادة الترتيب — يكتب offer_sort_order (الأعلى = الأول). Filament يوسّع
            // تلقائيًّا لكل الصفوف أثناء السحب.
            ->reorderable('offer_sort_order', $canManage)
            ->columns([
                // عمود رقم قابل للكتابة (بديل عن السحب حين تكثر العروض). الأصغر = الأول.
                // كتابة رقم = نقل الكتاب لتلك الرتبة وإعادة ترقيم الباقي 1..N بلا تعارض.
                Tables\Columns\TextInputColumn::make('offer_sort_order')
                    ->label('الترتيب')
                    ->type('number')
                    ->rules(['integer', 'min:1'])
                    ->disabled(! $canManage)
                    ->updateStateUsing(function (Book $record, $state): void {
                        Book::moveToOfferPosition((int) $record->getKey(), (int) $state);
                    }),

                Tables\Columns\ImageColumn::make('cover_image')
                    ->label('الغلاف')
                    ->disk('public')
                    ->square(),

                Tables\Columns\TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('author')
                    ->label('المؤلف')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('old_price')
                    ->label('قبل الخصم')
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 0).' ج.م' : '—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('السعر')
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 0).' ج.م' : '—'),
            ]);
    }
}
