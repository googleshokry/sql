<?php

namespace App\Services\Otp;

/**
 * Bind a real provider (SMS Misr, Vonage, WhatsApp Cloud API, ...) in AppServiceProvider.
 * LogSmsSender is the default for local development.
 */
interface SmsSender
{
    public function send(string $phone, string $message): void;
}
