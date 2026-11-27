<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\IsDocumentItem;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceItem extends Model
{
    use BelongsToCompany, IsDocumentItem;

    public const DOCUMENT_MODEL = PurchaseInvoice::class;

    protected $guarded = [];
}
