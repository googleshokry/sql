<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Accounting
{
    protected static array $cache = [];

    /** حساب نظامي بالمفتاح (receivable, payable, sales, cogs, inventory, vat_output ...) */
    public static function account(string $key): Account
    {
        $cacheKey = Tenancy::id().':'.$key;
        if (isset(static::$cache[$cacheKey]) && static::$cache[$cacheKey]->exists) {
            return static::$cache[$cacheKey];
        }

        return static::$cache[$cacheKey] = Account::query()->where('system_key', $key)->firstOr(
            fn () => throw new BusinessException(__('الحساب النظامي [:key] غير موجود. شغّل: php artisan db:seed --class=ChartOfAccountsSeeder', ['key' => $key]))
        );
    }

    public static function flushCache(): void
    {
        static::$cache = [];
    }

    public static function nextChildCode(Account $parent): string
    {
        $last = Account::query()->where('parent_id', $parent->id)->orderByDesc('code')->value('code');

        return $last ? (string) ((int) $last + 1) : $parent->code.'01';
    }

    /**
     * يستبدل القيد الآلي المرتبط بالمستند بقيد جديد.
     *
     * @param  array<int, array{account_id:int, debit?:float, credit?:float, party?:?Model, memo?:string}>  $lines
     */
    public static function sync(Model $source, $date, string $description, array $lines): ?JournalEntry
    {
        return DB::transaction(function () use ($source, $date, $description, $lines) {
            static::clear($source);

            $lines = collect($lines)
                ->map(fn ($l) => $l + ['debit' => 0, 'credit' => 0])
                ->map(function ($l) {
                    // تحويل القيم السالبة للجانب المقابل
                    $net = round((float) $l['debit'] - (float) $l['credit'], 2);
                    $l['debit'] = $net > 0 ? $net : 0;
                    $l['credit'] = $net < 0 ? -$net : 0;

                    return $l;
                })
                ->filter(fn ($l) => $l['debit'] > 0 || $l['credit'] > 0)
                ->values();

            if ($lines->isEmpty()) {
                return null;
            }

            $debit = round($lines->sum('debit'), 2);
            $credit = round($lines->sum('credit'), 2);
            if (abs($debit - $credit) >= 0.01) {
                throw new BusinessException(__('القيد غير متوازن (مدين :debit / دائن :credit)', ['debit' => $debit, 'credit' => $credit]));
            }

            $entry = JournalEntry::create([
                'date' => $date,
                'description' => mb_substr($description, 0, 250),
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'branch_id' => $source->branch_id ?? null,
                'is_auto' => true,
                'total' => $debit,
            ]);

            foreach ($lines as $l) {
                $party = $l['party'] ?? null;
                $entry->lines()->create([
                    'account_id' => $l['account_id'],
                    'debit' => $l['debit'],
                    'credit' => $l['credit'],
                    'party_type' => $party?->getMorphClass(),
                    'party_id' => $party?->getKey(),
                    'memo' => $l['memo'] ?? null,
                ]);
            }

            return $entry;
        });
    }

    public static function clear(Model $source): void
    {
        JournalEntry::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('is_auto', true)
            ->get()
            ->each(fn (JournalEntry $e) => $e->delete());
    }
}
