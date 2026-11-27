<?php

namespace App\Models;

use App\Support\Tenancy;
use Database\Factories\UserFactory;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_RECEPTIONIST = 'receptionist';

    public const ROLES = [
        self::ROLE_ADMIN => 'مدير النظام',
        self::ROLE_MANAGER => 'مدير الجيم',
        self::ROLE_RECEPTIONIST => 'موظف استقبال',
    ];

    /** Translated labels. */
    public static function roles(): array
    {
        return array_map(fn (string $label) => __($label), self::ROLES);
    }

    protected $fillable = ['name', 'email', 'phone', 'password', 'is_active', 'is_super_admin', 'locale'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    public function gyms(): BelongsToMany
    {
        return $this->belongsToMany(Gym::class)->withPivot('role')->withTimestamps();
    }

    // ---- Filament ----

    public function canAccessPanel(Panel $panel): bool
    {
        if (! ($this->is_active ?? true)) {
            return false;
        }

        return $panel->getId() === 'super' ? (bool) $this->is_super_admin : true;
    }

    public function getTenants(Panel $panel): array|Collection
    {
        return $this->gyms;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->gyms()->whereKey($tenant->getKey())->exists();
    }

    // ---- Roles (per gym) ----

    /** The user's role in the given gym (defaults to the current one). */
    public function roleIn(?Gym $gym = null): ?string
    {
        $gym ??= Tenancy::gym() ?? Filament::getTenant();
        if (! $gym) {
            return null;
        }

        return $this->gyms->firstWhere('id', $gym->getKey())?->pivot?->role;
    }

    public function getRoleAttribute(): ?string
    {
        return $this->roleIn();
    }

    public function isAdmin(): bool
    {
        return $this->roleIn() === self::ROLE_ADMIN;
    }

    /** Admins and managers can see finances, reports and settings. */
    public function isManager(): bool
    {
        return in_array($this->roleIn(), [self::ROLE_ADMIN, self::ROLE_MANAGER], true);
    }
}
