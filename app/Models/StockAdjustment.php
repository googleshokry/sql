<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** الجرد والتسويات المخزنية */
class StockAdjustment extends Model
{
    use HasDocumentNumber;

    public const NUMBER_PREFIX = 'ADJ';

    protected $guarded = [];

    protected $casts = ['date' => 'date', 'posted_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }
}
