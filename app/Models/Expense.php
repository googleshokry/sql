<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocumentNumber;
use App\Services\Accounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToCompany, HasDocumentNumber;

    public const NUMBER_PREFIX = 'EXP';

    protected $guarded = [];

    protected $casts = ['date' => 'date', 'amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2'];

    protected static function booted(): void
    {
        static::saving(function (self $e) {
            $e->tax_amount = round((float) $e->amount * (float) $e->tax_rate / 100, 2);
            $e->total = round((float) $e->amount + $e->tax_amount, 2);
            $e->branch_id ??= auth()->user()?->branch_id;
        });
        static::saved(function (self $e) {
            $expenseAccount = $e->category?->account_id ?? Accounting::account('general_expenses')->id;
            Accounting::sync($e, $e->date, __('مصروف: ').($e->category?->name ?? '').' '.($e->description ?? ''), [
                ['account_id' => $expenseAccount, 'debit' => $e->amount],
                ['account_id' => Accounting::account('vat_input')->id, 'debit' => $e->tax_amount],
                ['account_id' => $e->treasury->account_id, 'credit' => $e->total],
            ]);
        });
        static::deleted(fn (self $e) => Accounting::clear($e));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
