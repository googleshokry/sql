<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntryLine extends Model
{
    protected $guarded = [];

    protected $casts = ['debit' => 'decimal:2', 'credit' => 'decimal:2'];

    public function party(): MorphTo
    {
        return $this->morphTo();
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
