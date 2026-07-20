<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * دفتر المصروفات التشغيلية (المرحلة ٤ج / الدستور 0.8): إعلانات، رواتب، تغليف…
 * تُطرح من هامش المساهمة في القسم المالي لحساب صافي ربح النشاط.
 *
 * الصلاحيات (4.4): البادئة expenses (view/create/update/delete) — بيانات مالية
 * حسّاسة، ممنوحة لـ super_admin/admin فقط (RolePermissionSeeder)، لا للأدوار
 * التشغيلية. كل خطافات التصريح تمرّ عبر HasResourcePermissions خادميًا.
 */
class ExpenseResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_FINANCE;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'المصروفات';

    protected static ?string $modelLabel = 'مصروف';

    protected static ?string $pluralModelLabel = 'المصروفات';

    public static function permissionPrefix(): string
    {
        return 'expenses';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('البيان')
                        ->required()
                        ->maxLength(200),
                    Forms\Components\TextInput::make('category')
                        ->label('الفئة')
                        ->required()
                        ->maxLength(60)
                        ->datalist(['إعلانات', 'رواتب', 'تغليف', 'إيجار', 'شحن', 'أخرى'])
                        ->helperText('فئة حرة للتقسيم — مثل إعلانات أو رواتب.'),
                    Forms\Components\TextInput::make('amount')
                        ->label('المبلغ (ج.م)')
                        ->required()
                        ->numeric()
                        ->minValue(0),
                    Forms\Components\DatePicker::make('incurred_on')
                        ->label('تاريخ الصرف')
                        ->required()
                        // بتوقيت القاهرة لا UTC: التجميع لاحقًا بيوم القاهرة، فمصروف
                        // منتصف الليل يجب أن يُنسب لليوم المحلي الصحيح لا للسابق.
                        ->default(now('Africa/Cairo')->toDateString()),
                    Forms\Components\Textarea::make('note')
                        ->label('ملاحظة')
                        ->maxLength(500)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('incurred_on')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('البيان')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('الفئة')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('EGP')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('الإجمالي')->money('EGP')),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('سجّله')
                    ->toggleable()
                    ->placeholder('—'),
            ])
            ->defaultSort('incurred_on', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('الفئة')
                    ->options(fn (): array => Expense::query()
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
