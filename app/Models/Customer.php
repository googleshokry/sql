<?php

namespace App\Models;

use App\Models\Concerns\HasPartyCode;
use App\Services\Accounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Customer extends Model
{
    use HasPartyCode;

    public const CODE_PREFIX = 'C';

    /** مفتاح الحساب الرئيسي في دليل الحسابات */
    public const ACCOUNT_KEY = 'receivable';

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'opening_balance' => 'decimal:2'];

    protected static function booted(): void
    {
        static::saved(function (self $party) {
            $amount = (float) $party->opening_balance;
            Accounting::sync($party, now()->startOfYear()->toDateString(), __('رصيد افتتاحي - ').$party->name, [
                ['account_id' => Accounting::account(self::ACCOUNT_KEY)->id, 'debit' => $amount, 'party' => $party],
                ['account_id' => Accounting::account('opening_equity')->id, 'credit' => $amount],
            ]);
        });
    }

    public function ledgerLines(): MorphMany
    {
        return $this->morphMany(JournalEntryLine::class, 'party');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'party');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }

    public function installmentPlans(): HasMany
    {
        return $this->hasMany(InstallmentPlan::class);
    }

    /** الرصيد: موجب = مستحق لنا على العميل */
    public function balance(?string $to = null): float
    {
        $q = $this->ledgerLines()
            ->where('account_id', Accounting::account(self::ACCOUNT_KEY)->id)
            ->when($to, fn ($q) => $q->whereHas('entry', fn ($e) => $e->whereDate('date', '<=', $to)));

        $debit = (float) (clone $q)->sum('debit');
        $credit = (float) $q->sum('credit');

        return round(self::ACCOUNT_KEY === 'receivable' ? $debit - $credit : $credit - $debit, 2);
    }
}
