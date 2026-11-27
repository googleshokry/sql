<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public const TTL_SECONDS = 300;

    public const MAX_ATTEMPTS = 5;

    public function __construct(private SmsSender $sms) {}

    /** Egyptian numbers: 01xxxxxxxxx / +201xxxxxxxxx / 00201xxxxxxxxx -> +201xxxxxxxxx */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', strtr($phone, array_combine(mb_str_split('٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹'), str_split('01234567890123456789'))));

        return match (true) {
            str_starts_with($digits, '0020') => '+'.substr($digits, 2),
            str_starts_with($digits, '20') => '+'.$digits,
            str_starts_with($digits, '0') => '+2'.$digits,
            default => '+'.$digits,
        };
    }

    public function send(string $phone): void
    {
        $code = (string) random_int(100000, 999999);

        Cache::put($this->key($phone), ['hash' => Hash::make($code), 'attempts' => 0], self::TTL_SECONDS);

        $this->sms->send($phone, __('api.otp_message', ['code' => $code]));
    }

    public function verify(string $phone, string $code): bool
    {
        $entry = Cache::get($this->key($phone));

        if (! $entry || $entry['attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($code, $entry['hash'])) {
            $entry['attempts']++;
            Cache::put($this->key($phone), $entry, self::TTL_SECONDS);

            return false;
        }

        Cache::forget($this->key($phone));

        return true;
    }

    private function key(string $phone): string
    {
        return 'otp:'.sha1($phone);
    }
}
