<?php

namespace App\Models;

use App\Models\Concerns\IsDocument;
use App\Models\Concerns\IsPayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalesInvoice extends Model
{
    use IsDocument, IsPayable;

    public const NUMBER_PREFIX = 'INV';

    public const ITEM_MODEL = SalesInvoiceItem::class;

    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function party(): ?Model
    {
        return $this->customer;
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function installmentPlan(): HasOne
    {
        return $this->hasOne(InstallmentPlan::class);
    }
}
