<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    public const DEFAULTS = [
        'company_name' => 'شركتي',
        'company_name_en' => 'My Company',
        'tax_number' => '',
        'commercial_register' => '',
        'phone' => '',
        'email' => '',
        'address' => '',
        'logo' => null,
        'currency' => 'ر.س',
        'default_tax_rate' => '15',
        'allow_negative_stock' => '0',
        'invoice_terms' => 'البضاعة المباعة لا ترد ولا تستبدل بعد 14 يوم.',
        'invoice_footer' => 'شكراً لتعاملكم معنا',
        'zatca_qr' => '1',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $companyId = Tenancy::id();
        $all = $companyId ? Cache::rememberForever(static::cacheKey($companyId), fn () => static::query()->pluck('value', 'key')->all()) : [];

        return $all[$key] ?? $default ?? (static::DEFAULTS[$key] ?? null);
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => is_array($value) ? json_encode($value) : $value]);
        }
        Cache::forget(static::cacheKey(Tenancy::id()));
    }

    public static function cacheKey(?int $companyId): string
    {
        return 'settings.'.$companyId;
    }

    public static function values(): array
    {
        return array_merge(static::DEFAULTS, static::query()->pluck('value', 'key')->all());
    }
}
