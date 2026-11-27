<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    use HasDocumentNumber;

    public const NUMBER_PREFIX = 'JE';

    protected $guarded = [];

    protected $casts = ['date' => 'date', 'is_auto' => 'boolean', 'total' => 'decimal:2'];

    protected static function booted(): void
    {
        static::creating(fn ($e) => $e->created_by ??= auth()->id());
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function refreshTotal(): void
    {
        $this->forceFill(['total' => $this->lines()->sum('debit')])->saveQuietly();
    }

    public function isBalanced(): bool
    {
        return abs((float) $this->lines()->sum('debit') - (float) $this->lines()->sum('credit')) < 0.01;
    }
}
