<?php

namespace App\Models;

use App\Support\Tenancy;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['company_id', 'name', 'email', 'password', 'role', 'branch_id', 'is_active', 'locale', 'is_super_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLES = [
        'admin' => 'مدير النظام',
        'manager' => 'مدير فرع',
        'accountant' => 'محاسب',
        'sales' => 'مبيعات / كاشير',
        'purchases' => 'مشتريات',
        'storekeeper' => 'أمين مخزن',
        'hr' => 'موارد بشرية',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
        'is_super_admin' => false,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected static function booted(): void
    {
        // users created inside a company (e.g. from the users screen) belong to it
        static::creating(function (User $user) {
            if (! $user->company_id && ! $user->is_super_admin) {
                $user->company_id = Tenancy::id();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Platform owners use /platform; company users use /admin. */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $panel->getId() === 'platform' ? (bool) $this->is_super_admin : $this->company_id !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasRole(string ...$roles): bool
    {
        return $this->isAdmin() || in_array($this->role, $roles, true);
    }
}
