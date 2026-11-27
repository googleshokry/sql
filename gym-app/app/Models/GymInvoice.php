<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** An invoice the SaaS issues to a gym for its subscription. */
class GymInvoice extends Model
{
    public const STATUSES = ['unpaid' => 'غير مدفوعة', 'paid' => 'مدفوعة', 'void' => 'ملغاة'];

    protected $guarded = ['id'];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public static function statuses(): array
    {
        return array_map(fn (string $label) => __($label), self::STATUSES);
    }

    protected static function booted(): void
    {
        static::creating(fn (GymInvoice $i) => $i->number ??= 'TMP-'.Str::uuid());
        static::created(function (GymInvoice $i) {
            $i->number = 'INV-'.now()->format('Y').'-'.str_pad((string) $i->id, 5, '0', STR_PAD_LEFT);
            $i->saveQuietly();
        });
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class)->withTrashed();
    }

    public function saasPlan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class);
    }

    /** Create an invoice for the gym's next period. */
    public static function issueFor(Gym $gym, ?SaasPlan $plan = null, ?string $cycle = null): self
    {
        $plan ??= $gym->saasPlan;
        $cycle ??= $gym->billing_cycle ?: 'monthly';
        $start = $gym->subscription_ends_at?->isFuture() ? $gym->subscription_ends_at->copy() : now();
        $end = $cycle === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonthNoOverflow();

        return static::create([
            'gym_id' => $gym->id,
            'saas_plan_id' => $plan?->id,
            'amount' => $plan?->priceFor($cycle) ?? 0,
            'currency' => $plan?->currency ?? 'EGP',
            'billing_cycle' => $cycle,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
        ]);
    }

    /** Mark paid and extend the gym's subscription. */
    public function markPaid(?string $method = null, ?string $reference = null): void
    {
        if ($this->status === 'paid') {
            return;
        }
        $this->update(['status' => 'paid', 'paid_at' => now(), 'method' => $method, 'reference' => $reference]);
        $this->gym->extendSubscription($this->billing_cycle, $this->saasPlan);
    }
}
