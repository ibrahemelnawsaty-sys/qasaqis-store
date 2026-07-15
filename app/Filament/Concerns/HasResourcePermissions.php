<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Uniform, SERVER-SIDE permission enforcement for Filament Resources
 * (constitution 4.4 / anti-pattern 13 — never rely on hiding the button).
 *
 * A Resource opts in by using this trait and declaring its atomic-permission
 * prefix from docs/04 (e.g. "products", "orders", "coupons"):
 *
 *     class BookResource extends Resource
 *     {
 *         use HasResourcePermissions;
 *
 *         public static function permissionPrefix(): string
 *         {
 *             return 'products';
 *         }
 *     }
 *
 * The trait then maps Filament's authorization hooks onto the atomic
 * permissions "{prefix}.{action}":
 *
 *     view any / view  → {prefix}.view
 *     create           → {prefix}.create
 *     edit             → {prefix}.update
 *     delete / bulk    → {prefix}.delete
 *
 * Checks go through the Gate ($user->can()), so BOTH spatie's permission
 * resolution AND the super_admin Gate::before bypass (AppServiceProvider) apply
 * automatically — super_admin passes without being granted anything explicitly.
 *
 * Scope-limited roles (e.g. «support» restricted by allowed products) layer an
 * additional per-record Policy on top; this trait is the baseline coarse gate,
 * not a replacement for those record-level checks.
 */
trait HasResourcePermissions
{
    /**
     * The atomic-permission prefix for this resource (docs/04 §3).
     * Declared explicitly per resource — never guessed — to avoid inventing
     * permission names (constitution 1.1).
     */
    abstract public static function permissionPrefix(): string;

    /**
     * Resolve "{prefix}.{action}" against the authenticated user via the Gate.
     */
    protected static function userCan(string $action): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->can(static::permissionPrefix() . '.' . $action);
    }

    public static function canViewAny(): bool
    {
        return static::userCan('view');
    }

    public static function canView(Model $record): bool
    {
        return static::userCan('view');
    }

    public static function canCreate(): bool
    {
        return static::userCan('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCan('update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCan('delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::userCan('delete');
    }

    // الاستعادة والحذف النهائي (softDeletes) — عمليات مدمّرة تُربط بصلاحية الحذف
    // خادميًا (بند 4.4)؛ بدونها تُرجع Filament true افتراضيًا لأي مستخدم يصل للمورد.
    public static function canRestore(Model $record): bool
    {
        return static::userCan('delete');
    }

    public static function canRestoreAny(): bool
    {
        return static::userCan('delete');
    }

    public static function canForceDelete(Model $record): bool
    {
        return static::userCan('delete');
    }

    public static function canForceDeleteAny(): bool
    {
        return static::userCan('delete');
    }
}
