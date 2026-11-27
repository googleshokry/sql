<?php

namespace App\Models;

use App\Models\Concerns\IsDocumentItem;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceItem extends Model
{
    use IsDocumentItem;

    public const DOCUMENT_MODEL = PurchaseInvoice::class;

    protected $guarded = [];
}
