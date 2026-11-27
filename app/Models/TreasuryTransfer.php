<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocumentNumber;
use App\Services\Accounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasuryTransfer extends Model
{
    use BelongsToCompany, HasDocumentNumber;

    public const NUMBER_PREFIX = 'TT';

    protected $guarded = [];

    protected $casts = ['date' => 'date', 'amount' => 'decimal:2'];

    protected static function booted(): void
    {
        static::saved(function (self $t) {
            Accounting::sync($t, $t->date, __('تحويل من :from إلى :to', ['from' => $t->from->name, 'to' => $t->to->name]), [
                ['account_id' => $t->to->account_id, 'debit' => $t->amount],
                ['account_id' => $t->from->account_id, 'credit' => $t->amount],
            ]);
        });
        static::deleted(fn (self $t) => Accounting::clear($t));
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Treasury::class, 'from_treasury_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Treasury::class, 'to_treasury_id');
    }
}
