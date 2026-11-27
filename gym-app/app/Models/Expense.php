<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToGym;

    protected $guarded = ['id'];

    protected $casts = ['spent_at' => 'date', 'amount' => 'decimal:2'];

    protected static function booted(): void
    {
        static::creating(fn (Expense $e) => $e->user_id ??= auth()->id());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
