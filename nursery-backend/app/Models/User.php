<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasLocalePreference, HasTenants
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /** Roles allowed into the web nursery panel. Teachers and guardians use the mobile app. */
    public const PANEL_ROLES = ['nursery_admin', 'receptionist'];

    protected $fillable = ['name', 'email', 'phone', 'password', 'locale', 'avatar_path'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    /** Always store phones in one format so OTP lookup matches. */
    protected function phone(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            set: fn (?string $value) => blank($value) ? null : \App\Services\Otp\OtpService::normalizePhone($value),
        );
    }

    public function nurseries(): BelongsToMany
    {
        return $this->belongsToMany(Nursery::class)->withPivot('branch_id')->withTimestamps();
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function belongsToNursery(Nursery|int $nursery): bool
    {
        $id = $nursery instanceof Nursery ? $nursery->getKey() : $nursery;

        return $this->nurseries()->whereKey($id)->exists();
    }

    /** Role names this user holds inside one nursery (spatie teams). */
    public function rolesIn(Nursery $nursery): Collection
    {
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($nursery->getKey());
        $this->unsetRelation('roles');
        $names = $this->roles()->pluck('name');

        $registrar->setPermissionsTeamId($previous);
        $this->unsetRelation('roles');

        return $names;
    }

    // --- Filament ---

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'platform' => (bool) $this->is_super_admin,
            'app' => $this->nurseries()->exists(),
            default => false,
        };
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->nurseries()->get()
            ->filter(fn (Nursery $n) => $this->rolesIn($n)->intersect(self::PANEL_ROLES)->isNotEmpty())
            ->values();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Nursery
            && $this->belongsToNursery($tenant)
            && $this->rolesIn($tenant)->intersect(self::PANEL_ROLES)->isNotEmpty();
    }

    // --- Localization (notifications are sent in each user's own language) ---

    public function preferredLocale(): string
    {
        return $this->locale ?: config('app.locale');
    }
}
