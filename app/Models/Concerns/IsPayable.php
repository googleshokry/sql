<?php

namespace App\Models\Concerns;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** فواتير المبيعات والمشتريات التي تُسدَّد بسندات قبض/صرف */
trait IsPayable
{
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function refreshPaymentStatus(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $returns = method_exists($this, 'returns') ? (float) $this->returns()->whereNotNull('posted_at')->whereNull('treasury_id')->sum('total') : 0;
        $due = round((float) $this->total - $returns, 2);

        $this->forceFill([
            'paid_amount' => $paid,
            'payment_status' => match (true) {
                $paid <= 0 && $due > 0 => 'unpaid',
                $paid + 0.009 >= $due => 'paid',
                default => 'partial',
            },
        ])->saveQuietly();
    }

    public function remainingAmount(): float
    {
        $returns = method_exists($this, 'returns') ? (float) $this->returns()->whereNotNull('posted_at')->whereNull('treasury_id')->sum('total') : 0;

        return max(round((float) $this->total - $returns - (float) $this->paid_amount, 2), 0);
    }
}
