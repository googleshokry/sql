<?php

namespace App\Models;

use App\Models\Concerns\IsDocumentItem;
use Illuminate\Database\Eloquent\Model;

class SalesInvoiceItem extends Model
{
    use IsDocumentItem;

    public const DOCUMENT_MODEL = SalesInvoice::class;

    protected $guarded = [];
}
