<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Installment extends Model
{
    protected $guarded = [];

    protected $casts = ['due_date' => 'date', 'paid_at' => 'date', 'amount' => 'decimal:2', 'paid_amount' => 'decimal:2'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isPaid(): bool
    {
        return (float) $this->paid_amount + 0.009 >= (float) $this->amount;
    }

    public function isOverdue(): bool
    {
        return ! $this->isPaid() && $this->due_date->isPast();
    }

    /** تحصيل القسط: ينشئ سند قبض من العميل */
    public function collect(int $treasuryId, ?float $amount = null, ?string $date = null): Payment
    {
        $plan = $this->plan;
        $amount ??= (float) $this->amount - (float) $this->paid_amount;

        $payment = Payment::create([
            'direction' => 'in',
            'date' => $date ?? now()->toDateString(),
            'treasury_id' => $treasuryId,
            'party_type' => Customer::class,
            'party_id' => $plan->customer_id,
            'payable_type' => $plan->sales_invoice_id ? SalesInvoice::class : null,
            'payable_id' => $plan->sales_invoice_id,
            'amount' => $amount,
            'notes' => __('تحصيل القسط رقم :sequence - :plan', ['sequence' => $this->sequence, 'plan' => $plan->number]),
        ]);

        $this->update([
            'paid_amount' => (float) $this->paid_amount + $amount,
            'paid_at' => $payment->date,
            'payment_id' => $payment->id,
        ]);
        $plan->refreshStatus();

        return $payment;
    }
}
