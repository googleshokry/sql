<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentNumber;
use App\Services\Accounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** سند قبض (in) أو سند صرف (out) */
class Payment extends Model
{
    use HasDocumentNumber;

    public const NUMBER_PREFIX = 'PAY';

    public const METHODS = ['cash' => 'نقدي', 'card' => 'شبكة / بطاقة', 'bank_transfer' => 'تحويل بنكي', 'cheque' => 'شيك', 'wallet' => 'محفظة إلكترونية'];

    protected $guarded = [];

    protected $casts = ['date' => 'date', 'amount' => 'decimal:2'];

    protected static function booted(): void
    {
        static::creating(function (Payment $p) {
            $p->created_by ??= auth()->id();
            $p->number = static::nextNumber($p->direction === 'in' ? 'RCV' : 'PAY');
        });

        static::saved(function (Payment $p) {
            $p->post();
            static::refreshPayable($p->payable);
            if ($p->wasChanged('payable_id') && $p->getOriginal('payable_id')) {
                static::refreshPayable($p->getOriginal('payable_type')::find($p->getOriginal('payable_id')));
            }
        });

        static::deleted(function (Payment $p) {
            Accounting::clear($p);
            static::refreshPayable($p->payable);
        });
    }

    protected static function refreshPayable(?Model $payable): void
    {
        if ($payable && method_exists($payable, 'refreshPaymentStatus')) {
            $payable->refreshPaymentStatus();
        }
    }

    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function party(): MorphTo
    {
        return $this->morphTo();
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /** الحساب المقابل للخزينة */
    public function counterAccountId(): int
    {
        if ($this->payable instanceof SalesInvoice || $this->payable instanceof SalesReturn) {
            return Accounting::account('receivable')->id;
        }
        if ($this->payable instanceof PurchaseInvoice || $this->payable instanceof PurchaseReturn) {
            return Accounting::account('payable')->id;
        }
        if ($this->party instanceof Customer || $this->party instanceof Supplier) {
            return Accounting::account($this->party::ACCOUNT_KEY)->id;
        }
        if ($this->party instanceof Employee) {
            return Accounting::account('employee_advances')->id;
        }

        return $this->account_id ?? Accounting::account($this->direction === 'in' ? 'other_revenue' : 'general_expenses')->id;
    }

    public function post(): void
    {
        $treasury = ['account_id' => $this->treasury->account_id];
        $counter = ['account_id' => $this->counterAccountId(), 'party' => $this->party];
        $amount = (float) $this->amount;

        $lines = $this->direction === 'in'
            ? [$treasury + ['debit' => $amount], $counter + ['credit' => $amount]]
            : [$counter + ['debit' => $amount], $treasury + ['credit' => $amount]];

        $label = $this->direction === 'in' ? 'سند قبض' : 'سند صرف';
        Accounting::sync($this, $this->date, trim("$label {$this->number} ".($this->party?->name ?? '').' '.($this->notes ?? '')), $lines);
    }
}
