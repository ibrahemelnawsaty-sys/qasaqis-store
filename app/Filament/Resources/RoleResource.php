<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\RoleResource\Pages;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * View/edit spatie roles and their permission grants. Reading needs roles.view;
 * writing needs roles.manage (docs/04 §3.6) — in practice super_admin only, since
 * no other role is granted roles.manage in the seeder.
 *
 * The «super_admin» role is protected: it can never be deleted (its power comes
 * from the Gate::before bypass, not stored permissions, so editing it is inert).
 */
class RoleResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_USERS_PERMISSIONS;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'الأدوار والصلاحيات';

    protected static ?string $modelLabel = 'دور';

    protected static ?string $pluralModelLabel = 'الأدوار';

    protected static ?string $recordTitleAttribute = 'name';

    public static function permissionPrefix(): string
    {
        return 'roles';
    }

    // ----- Authorization (roles.view to read, roles.manage to write) ---------

    public static function canCreate(): bool
    {
        return static::userCan('manage');
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCan('manage');
    }

    public static function canDelete(Model $record): bool
    {
        // The super_admin role must never be removed (anti-pattern: breaking the
        // top-level bypass identity). All seven seeded roles are also safer kept.
        return static::userCan('manage') && $record->name !== 'super_admin';
    }

    public static function canDeleteAny(): bool
    {
        return static::userCan('manage');
    }

    /**
     * Permissions the current actor is allowed to grant to a role. super_admin
     * (via the Gate::before bypass) may grant EVERY permission; everyone else may
     * grant ONLY the permissions they themselves hold — so a mere roles.manage
     * holder cannot escalate a role beyond their own authority (privilege
     * escalation). This is the single source of truth used by both the form and
     * the create/edit pages, so a tampered request cannot inject a permission the
     * actor lacks. Mirrors UserResource::assignableRoleNames().
     *
     * @return array<string, string>
     */
    public static function assignablePermissionNames(): array
    {
        $actor = Auth::user();

        if ($actor === null) {
            return [];
        }

        if ($actor->hasRole('super_admin')) {
            return Permission::query()->orderBy('name')->pluck('name', 'name')->all();
        }

        return $actor->getAllPermissions()
            ->sortBy('name')
            ->pluck('name', 'name')
            ->all();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('بيانات الدور')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('الاسم البرمجي للدور')
                        ->required()
                        ->maxLength(125)
                        ->unique(ignoreRecord: true)
                        // guard_name is part of the uniqueness key in spatie.
                        ->disabled(fn (?Role $record): bool => $record?->name === 'super_admin'),
                    // spatie requires a guard; the app's single web guard is used.
                    Forms\Components\Hidden::make('guard_name')
                        ->default('web'),
                ]),

            Forms\Components\Section::make('الصلاحيات')
                ->schema([
                    // Options are limited to what the actor may grant (see
                    // assignablePermissionNames). NOT a ->relationship() field: the
                    // grant is synced manually in CreateRole/EditRole so the limit is
                    // enforced server-side, not just by the rendered checkboxes.
                    Forms\Components\CheckboxList::make('permissions')
                        ->label('الصلاحيات الممنوحة')
                        ->options(fn (): array => static::assignablePermissionNames())
                        ->searchable()
                        ->bulkToggleable()
                        ->columns(3)
                        ->helperText('لا يلزم منح صلاحيات لدور «super_admin» — يتجاوز كل شيء تلقائيًا.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الدور')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('guard_name')
                    ->label('الحارس')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('عدد الصلاحيات')
                    ->counts('permissions'),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('عدد المستخدمين')
                    ->counts('users'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('أُنشئ')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
