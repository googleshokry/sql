<?php

namespace App\Models;

use App\Models\Concerns\IsDocumentItem;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use IsDocumentItem;

    public const DOCUMENT_MODEL = PurchaseOrder::class;

    protected $guarded = [];
}
