<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Sale extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'sold_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Sale $s) {
            $s->invoice_number ??= 'TMP-'.Str::uuid();
            $s->sold_at ??= now();
            $s->user_id ??= auth()->id();
        });
        static::created(function (Sale $s) {
            $s->invoice_number = 'S'.str_pad((string) $s->id, 6, '0', STR_PAD_LEFT);
            $s->saveQuietly();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recalculate(): void
    {
        $this->subtotal = $this->items()->sum('total');
        $this->total = max(0, $this->subtotal - (float) $this->discount);
        $this->saveQuietly();
    }
}
