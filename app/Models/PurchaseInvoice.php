<?php

namespace App\Models;

use App\Models\Concerns\IsDocument;
use App\Models\Concerns\IsPayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseInvoice extends Model
{
    use IsDocument, IsPayable;

    public const NUMBER_PREFIX = 'PI';

    public const ITEM_MODEL = PurchaseInvoiceItem::class;

    protected $guarded = [];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function party(): ?Model
    {
        return $this->supplier;
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }
}
