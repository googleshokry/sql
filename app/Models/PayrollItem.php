<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (self $i) {
            $i->net_salary = round((float) $i->basic_salary + (float) $i->allowances + (float) $i->commissions + (float) $i->bonuses
                - (float) $i->deductions - (float) $i->advance_deduction, 2);
        });
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
