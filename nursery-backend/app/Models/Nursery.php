<?php

namespace App\Models;

use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Nursery extends Model implements HasName
{
    use HasFactory, HasTranslations;

    public array $translatable = ['name'];

    /** Mirrors the column defaults so a freshly created model has them without a refresh. */
    protected $attributes = [
        'default_locale' => 'ar',
        'timezone' => 'Africa/Cairo',
        'status' => 'active',
    ];

    protected $fillable = [
        'name', 'slug', 'logo_path', 'default_locale', 'timezone',
        'plan_id', 'subscription_ends_at', 'status',
    ];

    protected function casts(): array
    {
        return ['subscription_ends_at' => 'datetime'];
    }

    public function getFilamentName(): string
    {
        return $this->getTranslation('name', app()->getLocale());
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->subscription_ends_at === null || $this->subscription_ends_at->isFuture());
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('branch_id')->withTimestamps();
    }

    // Filament tenancy resolves tenant-owned resources through relationships on the tenant.
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
