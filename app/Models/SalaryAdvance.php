<?php

namespace App\Models;

use App\Services\Accounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** سلفة موظف */
class SalaryAdvance extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date', 'amount' => 'decimal:2', 'monthly_deduction' => 'decimal:2', 'deducted_amount' => 'decimal:2'];

    protected static function booted(): void
    {
        static::saved(function (self $a) {
            Accounting::sync($a, $a->date, 'سلفة للموظف '.$a->employee->name, [
                ['account_id' => Accounting::account('employee_advances')->id, 'debit' => $a->amount, 'party' => $a->employee],
                ['account_id' => $a->treasury->account_id, 'credit' => $a->amount],
            ]);
        });
        static::deleted(fn (self $a) => Accounting::clear($a));
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class);
    }

    public function remaining(): float
    {
        return round((float) $this->amount - (float) $this->deducted_amount, 2);
    }

    public function nextDeduction(): float
    {
        $monthly = (float) $this->monthly_deduction > 0 ? (float) $this->monthly_deduction : (float) $this->amount;

        return min($monthly, $this->remaining());
    }
}
