<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\IsDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends Model
{
    use BelongsToCompany, IsDocument;

    public const NUMBER_PREFIX = 'QT';

    public const ITEM_MODEL = QuotationItem::class;

    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function party(): ?Model
    {
        return $this->customer;
    }
}
