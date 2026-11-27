<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** خطة تقسيط لعميل (مرتبطة اختيارياً بفاتورة مبيعات) */
class InstallmentPlan extends Model
{
    use BelongsToCompany, HasDocumentNumber;

    public const NUMBER_PREFIX = 'INS';

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'total_amount' => 'decimal:2',
        'down_payment' => 'decimal:2',
        'interest_percent' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::created(fn (self $plan) => $plan->generateInstallments());
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('sequence');
    }

    public function financedAmount(): float
    {
        $base = (float) $this->total_amount - (float) $this->down_payment;

        return round($base * (1 + (float) $this->interest_percent / 100), 2);
    }

    public function generateInstallments(): void
    {
        $this->installments()->delete();
        $count = max((int) $this->installments_count, 1);
        $amount = $this->financedAmount();
        $each = floor($amount / $count * 100) / 100;

        for ($i = 1; $i <= $count; $i++) {
            $due = $this->period === 'weekly'
                ? $this->start_date->copy()->addWeeks($i - 1)
                : $this->start_date->copy()->addMonthsNoOverflow($i - 1);
            $this->installments()->create([
                'sequence' => $i,
                'due_date' => $due,
                'amount' => $i === $count ? round($amount - $each * ($count - 1), 2) : $each,
            ]);
        }
    }

    public function paidAmount(): float
    {
        return (float) $this->installments()->sum('paid_amount');
    }

    public function refreshStatus(): void
    {
        if ($this->status === 'cancelled') {
            return;
        }
        $remaining = $this->installments()->whereColumn('paid_amount', '<', 'amount')->count();
        $this->update(['status' => $remaining === 0 ? 'completed' : 'active']);
    }
}
