<?php

namespace App\Support;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * رمز QR للفاتورة الضريبية المبسطة حسب متطلبات هيئة الزكاة والضريبة والجمارك (المرحلة الأولى).
 * TLV: 1 اسم البائع، 2 الرقم الضريبي، 3 وقت الفاتورة، 4 الإجمالي شامل الضريبة، 5 مبلغ الضريبة
 */
class Zatca
{
    public static function tlv(string $seller, string $vatNumber, string $timestamp, string $total, string $vat): string
    {
        $out = '';
        foreach ([1 => $seller, 2 => $vatNumber, 3 => $timestamp, 4 => $total, 5 => $vat] as $tag => $value) {
            $out .= chr($tag).chr(strlen($value)).$value;
        }

        return base64_encode($out);
    }

    public static function qrDataUri(string $data): string
    {
        return (new QRCode(new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => true,
            'scale' => 5,
            'addQuietzone' => true,
        ])))->render($data);
    }
}
