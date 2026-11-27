<?php

namespace App\Support;

use App\Models\Setting;

/** تحويل المبلغ إلى كلمات عربية (تفقيط) */
class Tafqeet
{
    protected const ONES = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة',
        'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];

    protected const TENS = ['', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];

    protected const HUNDREDS = ['', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

    /** [مفرد, مثنى, جمع] */
    protected const SCALES = [
        ['', '', ''],
        ['ألف', 'ألفان', 'آلاف'],
        ['مليون', 'مليونان', 'ملايين'],
        ['مليار', 'ملياران', 'مليارات'],
    ];

    public static function convert(float $amount, ?string $currency = null, ?string $fraction = null): string
    {
        $currency ??= static::currencyName();
        $fraction ??= str_contains($currency, 'ريال') ? 'هللة' : 'قرش';

        $whole = (int) floor(round($amount, 2));
        $cents = (int) round(($amount - $whole) * 100);

        $text = $whole === 0 ? 'صفر' : static::number($whole);
        $text .= ' '.$currency;
        if ($cents > 0) {
            $text .= ' و'.static::number($cents).' '.$fraction;
        }

        return 'فقط '.$text.' لا غير';
    }

    protected static function currencyName(): string
    {
        $symbol = (string) Setting::get('currency', 'ر.س');

        return match (true) {
            str_contains($symbol, 'ر.س'), str_contains($symbol, 'SAR') => 'ريال سعودي',
            str_contains($symbol, 'ج.م'), str_contains($symbol, 'EGP') => 'جنيه مصري',
            str_contains($symbol, 'د.إ'), str_contains($symbol, 'AED') => 'درهم إماراتي',
            str_contains($symbol, 'د.ك'), str_contains($symbol, 'KWD') => 'دينار كويتي',
            default => $symbol,
        };
    }

    public static function number(int $n): string
    {
        if ($n === 0) {
            return 'صفر';
        }

        $parts = [];
        $scale = 0;
        while ($n > 0) {
            $chunk = $n % 1000;
            if ($chunk > 0) {
                $parts[] = static::withScale($chunk, $scale);
            }
            $n = intdiv($n, 1000);
            $scale++;
        }

        return implode(' و', array_reverse($parts));
    }

    protected static function withScale(int $chunk, int $scale): string
    {
        if ($scale === 0) {
            return static::belowThousand($chunk);
        }
        [$one, $two, $many] = static::SCALES[$scale];

        return match (true) {
            $chunk === 1 => $one,
            $chunk === 2 => $two,
            $chunk >= 3 && $chunk <= 10 => static::belowThousand($chunk).' '.$many,
            default => static::belowThousand($chunk).' '.$one,
        };
    }

    protected static function belowThousand(int $n): string
    {
        $h = intdiv($n, 100);
        $rest = $n % 100;
        $words = [];
        if ($h) {
            $words[] = static::HUNDREDS[$h];
        }
        if ($rest) {
            if ($rest < 20) {
                $words[] = static::ONES[$rest];
            } else {
                $o = $rest % 10;
                $t = intdiv($rest, 10);
                $words[] = $o ? static::ONES[$o].' و'.static::TENS[$t] : static::TENS[$t];
            }
        }

        return implode(' و', $words);
    }
}
