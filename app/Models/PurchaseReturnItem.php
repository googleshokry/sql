<?php

namespace App\Models;

use App\Models\Concerns\IsDocumentItem;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    use IsDocumentItem;

    public const DOCUMENT_MODEL = PurchaseReturn::class;

    protected $guarded = [];
}
