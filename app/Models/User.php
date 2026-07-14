<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'category_id',
        'password',
        'is_active',
        'avatar_path',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * The seven administrative roles allowed into the Filament admin panel.
     * Storefront customers hold none of these and are therefore locked out.
     *
     * @var list<string>
     */
    public const ADMIN_ROLES = [
        'super_admin',
        'admin',
        'it',
        'support',
        'content_editor',
        'orders_manager',
        'marketing',
    ];

    /**
     * Server-side gate for the /admin panel (constitution 4.4): a user may enter
     * only if they hold any administrative role AND their account is active.
     * Fine-grained per-resource access is still enforced separately via each
     * Resource's permission checks (HasResourcePermissions).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active === true && $this->hasAnyRole(self::ADMIN_ROLES);
    }

    // ----- Relationships -------------------------------------------------

    /**
     * Default support scope: the category this user is limited to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Per-book / per-category scope rows for the "support" role.
     */
    public function supportProducts(): HasMany
    {
        return $this->hasMany(SupportUserProduct::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
