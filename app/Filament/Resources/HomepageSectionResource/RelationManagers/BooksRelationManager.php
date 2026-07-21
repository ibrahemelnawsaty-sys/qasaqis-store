<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomepageSectionResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * كتب القسم المثبّتة/المختارة. لقسم «يدوي» هي كل كتبه؛ لقسم تلقائي تظهر أولًا ثم
 * تُكمِل القاعدة (انظر HomepageSectionResolver). السحب يكتب عمود pivot `position`.
 *
 * الصلاحية: العرض بـ sections.view؛ الإرفاق/الفصل/السحب بـ sections.assign_product
 * (هذا غرضها بالضبط). نبوّب عبر ->visible لأنها إجراءات Attach/Detach لا CRUD عادي.
 */
class BooksRelationManager extends RelationManager
{
    protected static string $relationship = 'books';

    protected static ?string $title = 'الكتب المثبّتة / المختارة';

    protected static ?string $recordTitleAttribute = 'title';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('sections.view');
    }

    public function table(Table $table): Table
    {
        $canAssign = (bool) auth()->user()?->can('sections.assign_product');

        return $table
            ->recordTitleAttribute('title')
            // السحب يكتب homepage_section_book.position (books() تعرّف withPivot('position')).
            ->reorderable('position', $canAssign)
            ->columns([
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
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('إضافة كتاب')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['title', 'author'])
                    ->visible($canAssign),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->label('إزالة')
                    ->visible($canAssign),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ])->visible($canAssign),
            ]);
    }
}
