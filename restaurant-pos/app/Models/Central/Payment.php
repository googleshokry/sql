<?php

namespace App\Models\Central;

use Illuminate\Support\Facades\DB;

class Payment extends CentralModel
{
    protected $fillable = [
        'tenant_id', 'plan_id', 'amount', 'currency', 'months', 'method', 'status', 'reference',
        'gateway_order_id', 'gateway_transaction_id', 'notes', 'payload', 'paid_at',
    ];

    protected $casts = ['amount' => 'decimal:2', 'payload' => 'array', 'paid_at' => 'datetime'];

    public const METHODS = ['paymob', 'cash', 'instapay', 'wallet', 'bank'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /** تأكيد الدفع وتجديد اشتراك المطعم (مرة واحدة فقط) */
    public function markPaid(array $attributes = []): void
    {
        DB::connection($this->getConnectionName())->transaction(function () use ($attributes) {
            $fresh = static::lockForUpdate()->find($this->id);
            if ($fresh->status === 'paid') {
                return;
            }
            $fresh->update([...$attributes, 'status' => 'paid', 'paid_at' => now()]);
            $fresh->tenant->extendSubscription($fresh->plan, $fresh->months);
        });
        $this->refresh();
    }

    public static function methodLabel(?string $method): string
    {
        return match ($method) {
            'paymob' => __('دفع أونلاين (Paymob)'),
            'cash' => __('كاش'),
            'instapay' => 'InstaPay',
            'wallet' => __('محفظة'),
            'bank' => __('تحويل بنكي'),
            default => (string) $method,
        };
    }
}
