<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Support\Tenancy;

class Setting extends Model
{
    use BelongsToGym;

    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'gym_name' => 'Gym Manager',
        'gym_phone' => '',
        'gym_address' => '',
        'gym_logo' => null,
        'gym_about' => '',
        'working_hours' => '',
        'gym_location_url' => '',
        'gym_instagram' => '',
        'gym_facebook' => '',
        'currency' => 'ج.م',
        'expiry_warning_days' => 7,
        'auto_checkout_hours' => 4,
        'allow_checkin_with_debt' => true,
        'receipt_footer' => 'شكراً لاشتراككم معنا',
        'whatsapp_expiry_message' => 'مرحباً {name}، نود تذكيرك بأن اشتراكك في {gym} ينتهي بتاريخ {end_date}. يسعدنا تجديد اشتراكك 💪',
    ];

    /** Settings stored on the gym record itself rather than as key/value rows. */
    public const GYM_COLUMNS = [
        'gym_name' => 'name',
        'gym_phone' => 'phone',
        'gym_address' => 'address',
        'gym_logo' => 'logo',
        'currency' => 'currency',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::allSettings();

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    /** All settings of the current gym (defaults when no gym is active). */
    public static function allSettings(): array
    {
        $gym = Tenancy::gym();
        if (! $gym) {
            return self::DEFAULTS;
        }

        return Cache::rememberForever(static::cacheKey($gym->id), function () use ($gym) {
            $values = static::query()->where('gym_id', $gym->id)->pluck('value', 'key')->toArray();
            foreach (self::GYM_COLUMNS as $key => $column) {
                $values[$key] = $gym->{$column} ?? self::DEFAULTS[$key];
            }

            return array_merge(self::DEFAULTS, $values);
        });
    }

    public static function put(array $values): void
    {
        $gym = Tenancy::gym() ?? throw new \LogicException('No gym selected.');

        $gymColumns = [];
        foreach ($values as $key => $value) {
            if (isset(self::GYM_COLUMNS[$key])) {
                $gymColumns[self::GYM_COLUMNS[$key]] = $value;

                continue;
            }
            static::updateOrCreate(['gym_id' => $gym->id, 'key' => $key], ['value' => is_bool($value) ? (int) $value : $value]);
        }
        if ($gymColumns) {
            $gym->forceFill(array_filter($gymColumns, fn ($v, $k) => $v !== null || $k === 'logo', ARRAY_FILTER_USE_BOTH))->save();
        }
        static::flush($gym->id);
    }

    public static function flush(int $gymId): void
    {
        Cache::forget(static::cacheKey($gymId));
    }

    private static function cacheKey(int $gymId): string
    {
        return "gym_settings_{$gymId}";
    }

    public static function currency(): string
    {
        return __((string) static::get('currency'));
    }
}
