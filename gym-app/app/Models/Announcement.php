<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }

    /** Title in the requested language (falls back to the Arabic one). */
    public function localizedTitle(): string
    {
        return app()->getLocale() === 'en' && $this->title_en ? $this->title_en : $this->title;
    }

    public function localizedBody(): ?string
    {
        return app()->getLocale() === 'en' && $this->body_en ? $this->body_en : $this->body;
    }
}
