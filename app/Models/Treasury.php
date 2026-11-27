<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\Accounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Treasury extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean', 'opening_balance' => 'decimal:2'];

    public const TYPES = ['cash' => 'خزينة نقدية', 'bank' => 'حساب بنكي', 'wallet' => 'محفظة إلكترونية / شبكة'];

    protected static function booted(): void
    {
        // كل خزينة لها حساب خاص في دليل الحسابات تحت النقدية أو البنوك
        static::creating(function (Treasury $t) {
            if (! $t->account_id) {
                $parent = Accounting::account($t->type === 'bank' ? 'banks' : 'cash');
                $t->account_id = Account::create([
                    'code' => Accounting::nextChildCode($parent),
                    'name' => $t->name,
                    'type' => 'asset',
                    'parent_id' => $parent->id,
                ])->id;
            }
        });
        static::saved(function (Treasury $t) {
            $t->account?->update(['name' => $t->name]);
            Accounting::sync($t, now()->startOfYear()->toDateString(), __('رصيد افتتاحي - ').$t->name, [
                ['account_id' => $t->account_id, 'debit' => $t->opening_balance],
                ['account_id' => Accounting::account('opening_equity')->id, 'credit' => $t->opening_balance],
            ]);
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function balance(): float
    {
        return $this->account ? $this->account->balance() : 0;
    }
}
