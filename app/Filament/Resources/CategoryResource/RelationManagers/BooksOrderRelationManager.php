<?php

declare(strict_types=1);

namespace App\Filament\Resources\CategoryResource\RelationManagers;

use App\Models\Book;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ترتيب ظهور كتب القسم في المتجر — بالسحب. تعرض كل كتب القسم (الرئيسي + الإضافي،
 * تُوائمها SyncCategoryBookOrder عند فتح القسم)، والسحب يكتب category_book_positions.position.
 * لا إرفاق/فصل هنا: عضويّة الكتاب للقسم تُدار من صفحة الكتاب نفسه؛ هذه الشاشة للترتيب فقط.
 *
 * الصلاحية: العرض بـ categories.view؛ السحب بـ categories.manage (نفس صلاحيات المورد).
 */
class BooksOrderRelationManager extends RelationManager
{
    protected static string $relationship = 'orderedBooks';

    protected static ?string $title = 'ترتيب ظهور الكتب في القسم';

    protected static ?string $recordTitleAttribute = 'title';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('categories.view');
    }

    public function table(Table $table): Table
    {
        $canManage = (bool) auth()->user()?->can('categories.manage');

        return $table
            ->recordTitleAttribute('title')
            // اسحب لإعادة الترتيب — يكتب category_book_positions.position (الأعلى = الأول).
            // Filament يوسّع تلقائيًّا لكل الصفوف أثناء وضع السحب، فلا نُلغي التصفيح (يبقى
            // العرض خفيفًا للأقسام الكبيرة) — مطابقةً لمدير كتب أقسام الرئيسية.
            ->reorderable('position', $canManage)
            ->columns([
                // عمود رقم ترتيب قابل للكتابة مباشرةً (بديل عن السحب حين تكثر الكتب).
                // الأصغر = الأول. يقرأ/يكتب على عمود pivot (category_book_positions.position)
                // لا على الكتاب — عبر updateExistingPivot على علاقة orderedBooks.
                Tables\Columns\TextInputColumn::make('position')
                    ->label('الترتيب')
                    ->type('number')
                    ->rules(['integer', 'min:0'])
                    ->disabled(! $canManage)
                    ->getStateUsing(fn (Book $record): int => (int) ($record->pivot->position ?? 0))
                    // الكتابة على صفّ pivot المحمَّل مباشرةً (category_book_positions) — نفس آليّة
                    // السحب في Filament، بلا حاجة لحقن $livewire أو جلب القسم المالك.
                    ->updateStateUsing(function (Book $record, $state): void {
                        $record->pivot->update(['position' => (int) $state]);
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

                Tables\Columns\TextColumn::make('published_at')
                    ->label('تاريخ النشر')
                    ->date('Y/m/d')
                    ->placeholder('—')
                    ->toggleable(),
            ]);
    }
}
