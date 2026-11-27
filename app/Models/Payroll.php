<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** مسير الرواتب الشهري */
class Payroll extends Model
{
    use BelongsToCompany, HasDocumentNumber;

    public const NUMBER_PREFIX = 'PAYROLL';

    protected $guarded = [];

    protected $casts = ['posted_at' => 'datetime', 'total' => 'decimal:2'];

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class);
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }

    /** توليد بنود المسير من بيانات الموظفين النشطين والعمولات والسلف */
    public function generateItems(): void
    {
        $this->items()->delete();
        [$y, $m] = explode('-', $this->month);

        foreach (Employee::query()->where('is_active', true)->get() as $employee) {
            $commission = 0;
            if ((float) $employee->commission_percent > 0 && $employee->user_id) {
                $sales = SalesInvoice::query()->where('created_by', $employee->user_id)
                    ->whereNotNull('posted_at')->whereYear('date', $y)->whereMonth('date', $m)->sum('subtotal');
                $commission = round($sales * (float) $employee->commission_percent / 100, 2);
            }
            $absentDays = $employee->attendances()->where('status', 'absent')->whereYear('date', $y)->whereMonth('date', $m)->count();
            $deductions = round($absentDays * (float) $employee->basic_salary / 30, 2);
            $advance = (float) $employee->advances->sum(fn (SalaryAdvance $a) => $a->nextDeduction());

            $this->items()->create([
                'employee_id' => $employee->id,
                'basic_salary' => $employee->basic_salary,
                'allowances' => $employee->totalAllowances(),
                'commissions' => $commission,
                'bonuses' => 0,
                'deductions' => $deductions,
                'advance_deduction' => $advance,
            ]);
        }
        $this->refreshTotal();
    }

    public function refreshTotal(): void
    {
        $this->update(['total' => $this->items()->sum('net_salary')]);
    }
}
