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
        if (app()->getLocale() === 'en') {
            return static::english($amount);
        }

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

    protected const EN_ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
        'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    protected const EN_TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    protected const EN_SCALES = ['', 'thousand', 'million', 'billion'];

    /** Amount in English words, e.g. "Only one thousand two hundred Saudi Riyals and 50/100". */
    public static function english(float $amount): string
    {
        $whole = (int) floor(round($amount, 2));
        $cents = (int) round(($amount - $whole) * 100);

        $words = [];
        $scale = 0;
        $n = $whole;
        while ($n > 0) {
            $chunk = $n % 1000;
            if ($chunk > 0) {
                array_unshift($words, trim(static::englishBelowThousand($chunk).' '.static::EN_SCALES[$scale]));
            }
            $n = intdiv($n, 1000);
            $scale++;
        }

        $text = $whole === 0 ? 'zero' : implode(' ', $words);
        $text .= ' '.static::englishCurrency();
        if ($cents > 0) {
            $text .= ' and '.$cents.'/100';
        }

        return 'Only '.$text;
    }

    protected static function englishBelowThousand(int $n): string
    {
        $parts = [];
        if ($n >= 100) {
            $parts[] = static::EN_ONES[intdiv($n, 100)].' hundred';
            $n %= 100;
        }
        if ($n >= 20) {
            $parts[] = static::EN_TENS[intdiv($n, 10)].($n % 10 ? '-'.static::EN_ONES[$n % 10] : '');
        } elseif ($n > 0) {
            $parts[] = static::EN_ONES[$n];
        }

        return implode(' ', $parts);
    }

    protected static function englishCurrency(): string
    {
        $symbol = (string) Setting::get('currency', 'ر.س');

        return match (true) {
            str_contains($symbol, 'ر.س'), str_contains($symbol, 'SAR') => 'Saudi Riyals',
            str_contains($symbol, 'ج.م'), str_contains($symbol, 'EGP') => 'Egyptian Pounds',
            str_contains($symbol, 'د.إ'), str_contains($symbol, 'AED') => 'UAE Dirhams',
            str_contains($symbol, 'د.ك'), str_contains($symbol, 'KWD') => 'Kuwaiti Dinars',
            str_contains($symbol, 'ر.ق') => 'Qatari Riyals',
            str_contains($symbol, 'ر.ع') => 'Omani Rials',
            str_contains($symbol, 'د.أ') => 'Jordanian Dinars',
            default => $symbol,
        };
    }
}
