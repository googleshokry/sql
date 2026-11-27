<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\IsDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrder extends Model
{
    use BelongsToCompany, IsDocument;

    public const NUMBER_PREFIX = 'PO';

    public const ITEM_MODEL = PurchaseOrderItem::class;

    protected $guarded = [];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function party(): ?Model
    {
        return $this->supplier;
    }
}
