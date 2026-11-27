<?php

namespace App\Models;

use App\Exceptions\BusinessException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $guarded = [];

    protected $casts = ['is_group' => 'boolean', 'is_system' => 'boolean'];

    public const TYPES = [
        'asset' => 'أصول',
        'liability' => 'خصوم',
        'equity' => 'حقوق ملكية',
        'revenue' => 'إيرادات',
        'expense' => 'مصروفات',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Account $account) {
            if ($account->is_system || $account->lines()->exists() || $account->children()->exists()) {
                throw new BusinessException(__('لا يمكن حذف حساب نظامي أو عليه حركات أو له حسابات فرعية.'));
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function isDebitNature(): bool
    {
        return in_array($this->type, ['asset', 'expense']);
    }

    /** IDs الحساب وكل الحسابات الفرعية تحته */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $level = [$this->id];
        while ($level) {
            $level = static::query()->whereIn('parent_id', $level)->pluck('id')->all();
            $ids = array_merge($ids, $level);
        }

        return $ids;
    }

    public function balance(?string $from = null, ?string $to = null): float
    {
        $q = JournalEntryLine::query()
            ->whereIn('account_id', $this->descendantIds())
            ->whereHas('entry', function ($e) use ($from, $to) {
                $e->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
                    ->when($to, fn ($q) => $q->whereDate('date', '<=', $to));
            });
        $debit = (float) (clone $q)->sum('debit');
        $credit = (float) $q->sum('credit');

        return round($this->isDebitNature() ? $debit - $credit : $credit - $debit, 2);
    }
}
