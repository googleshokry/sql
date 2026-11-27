<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    public const METHODS = [
        'cash' => 'كاش',
        'card' => 'فيزا / بطاقة',
        'wallet' => 'محفظة إلكترونية (فودافون كاش ...)',
        'instapay' => 'إنستاباي',
        'bank' => 'تحويل بنكي',
    ];

    public const TYPES = [
        'subscription' => 'اشتراك',
        'personal_training' => 'تدريب خاص',
        'day_pass' => 'حصة يومية / زائر',
        'other' => 'أخرى',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $p) {
            $p->receipt_number ??= 'TMP-'.Str::uuid();
            $p->paid_at ??= now();
            $p->received_by ??= auth()->id();
            if ($p->subscription_id && ! $p->member_id) {
                $p->member_id = Subscription::find($p->subscription_id)?->member_id;
            }
        });
        static::created(function (Payment $p) {
            $p->receipt_number = 'R'.str_pad((string) $p->id, 6, '0', STR_PAD_LEFT);
            $p->saveQuietly();
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
