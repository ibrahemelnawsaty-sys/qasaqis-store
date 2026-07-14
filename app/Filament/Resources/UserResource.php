<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Manage admin users, spatie role assignment, activation, and the support scope
 * rows. Coarse gate via HasResourcePermissions (users.view / users.manage);
 * privilege-escalation guards are enforced server-side here and in the pages:
 *
 *  - Only super_admin may assign super_admin / admin roles (docs/04 §2.2).
 *  - A non-super_admin may not edit or delete a super_admin account.
 *  - Nobody may delete their own account.
 *
 * The «users» atomic prefix (docs/04 §3.6) exposes users.view + users.manage
 * only — there is no users.create/update/delete — so create/edit/delete all map
 * onto users.manage below rather than the trait's default {prefix}.{action}.
 */
class UserResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_USERS_PERMISSIONS;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'المستخدمون';

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمون';

    protected static ?string $recordTitleAttribute = 'name';

    public static function permissionPrefix(): string
    {
        return 'users';
    }

    // ----- Authorization (users.view for reading, users.manage for writing) --

    public static function canCreate(): bool
    {
        return static::userCan('manage');
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCan('manage') && static::canManageRecord($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCan('manage')
            && static::canManageRecord($record)
            && $record->getKey() !== Auth::id();
    }

    public static function canDeleteAny(): bool
    {
        return static::userCan('manage');
    }

    /**
     * A non-super_admin may not touch a super_admin account (docs/04 §2.2:
     * «admin لا يلمس السوبر أدمن»). super_admin may manage anyone.
     */
    protected static function canManageRecord(Model $record): bool
    {
        $actor = Auth::user();

        if ($actor === null) {
            return false;
        }

        if ($actor->hasRole('super_admin')) {
            return true;
        }

        return ! $record->hasRole('super_admin');
    }

    /**
     * Roles the current actor is allowed to grant. super_admin may grant any
     * role; everyone else may grant only roles strictly below «admin» (never
     * super_admin/admin) so they cannot raise anyone above their own authority.
     * This is the single source of truth used by both the form and the pages,
     * so the request cannot be tampered to inject a higher role.
     *
     * @return array<string, string>
     */
    public static function assignableRoleNames(): array
    {
        $all = Role::query()->orderBy('name')->pluck('name', 'name')->all();

        if (Auth::user()?->hasRole('super_admin') === true) {
            return $all;
        }

        unset($all['super_admin'], $all['admin']);

        return $all;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('بيانات الحساب')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('الاسم')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->label('البريد الإلكتروني')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('phone')
                        ->label('رقم الهاتف')
                        ->tel()
                        ->maxLength(20),
                    Forms\Components\TextInput::make('password')
                        ->label('كلمة المرور')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        // Required only when creating; on edit an empty field
                        // leaves the existing password untouched.
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)),
                    Forms\Components\Toggle::make('is_active')
                        ->label('حساب مفعّل')
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make('الأدوار والصلاحيات')
                ->schema([
                    Forms\Components\Select::make('roles')
                        ->label('الأدوار')
                        ->multiple()
                        ->options(fn (): array => static::assignableRoleNames())
                        ->native(false)
                        ->helperText('يُسند سوبر أدمن أي دور؛ أما غيره فيُسند الأدوار الأدنى فقط.'),
                ]),

            Forms\Components\Section::make('نطاق الدعم (منتجات محددة)')
                ->description('يُقيّد دور «الدعم» بالكتب أو الأقسام المحددة هنا فقط — يُفرض خادميًا.')
                ->schema([
                    Forms\Components\Repeater::make('supportProducts')
                        ->relationship()
                        ->label('عناصر النطاق')
                        ->schema([
                            Forms\Components\Select::make('book_id')
                                ->label('كتاب')
                                ->options(fn (): array => Book::query()->orderBy('title')->pluck('title', 'id')->all())
                                ->searchable()
                                ->native(false),
                            Forms\Components\Select::make('category_id')
                                ->label('قسم')
                                ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->native(false),
                        ])
                        ->columns(2)
                        ->addActionLabel('إضافة عنصر نطاق')
                        ->defaultItems(0),
                ])
                ->collapsed()
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('البريد')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('الهاتف')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('الأدوار')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('مفعّل')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('آخر دخول')
                    ->dateTime()
                    ->toggleable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('أُنشئ')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('الحالة'),
                Tables\Filters\SelectFilter::make('roles')
                    ->label('الدور')
                    ->relationship('roles', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('roles');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
