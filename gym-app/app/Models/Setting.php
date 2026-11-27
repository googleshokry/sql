<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
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

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::allSettings();

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function allSettings(): array
    {
        return Cache::rememberForever('gym_settings', function () {
            if (! Schema::hasTable('settings')) {
                return self::DEFAULTS;
            }

            return array_merge(self::DEFAULTS, static::query()->pluck('value', 'key')->toArray());
        });
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => is_bool($value) ? (int) $value : $value]);
        }
        Cache::forget('gym_settings');
    }

    public static function currency(): string
    {
        return __((string) static::get('currency'));
    }
}
