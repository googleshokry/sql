<?php

namespace App\Models;

use App\Models\Concerns\IsDocumentItem;
use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    use IsDocumentItem;

    public const DOCUMENT_MODEL = Quotation::class;

    protected $guarded = [];
}
