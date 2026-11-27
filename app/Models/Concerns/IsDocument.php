<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DocumentTotals;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * سلوك مشترك لكل المستندات ذات البنود (فواتير، مرتجعات، عروض أسعار، أوامر شراء).
 * الموديل يعرّف: public const ITEM_MODEL = SalesInvoiceItem::class;
 */
trait IsDocument
{
    use HasDocumentNumber;

    public static function bootIsDocument(): void
    {
        static::creating(function ($model) {
            $model->created_by ??= auth()->id();
            $model->branch_id ??= auth()->user()?->branch_id;
        });

        static::deleting(function ($model) {
            if ($model->isPosted()) {
                throw new \RuntimeException(__('لا يمكن حذف مستند مُرحّل. قم بإلغاء الترحيل أولاً.'));
            }
        });
    }

    public function initializeIsDocument(): void
    {
        $this->mergeCasts([
            'date' => 'date',
            'posted_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping' => 'decimal:2',
            'total' => 'decimal:2',
        ]);
    }

    public function items(): HasMany
    {
        return $this->hasMany(static::ITEM_MODEL);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted' || $this->posted_at !== null;
    }

    public function recalculate(): void
    {
        $this->loadMissing('items');
        $totals = DocumentTotals::calculate(
            $this->items->map(fn ($i) => $i->only(['quantity', 'unit_price', 'discount_percent', 'tax_rate']))->all(),
            $this->discount_type,
            (float) $this->discount_value,
            (float) $this->shipping,
        );

        $this->forceFill([
            'subtotal' => $totals['subtotal'],
            'discount_amount' => $totals['discount_amount'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
        ])->saveQuietly();
    }

    /** صافي البنود بعد الخصم وقبل الضريبة (يدخل في الإيراد/المخزون) */
    public function netAmount(): float
    {
        return round((float) $this->subtotal - (float) $this->discount_amount + (float) $this->shipping, 2);
    }
}
