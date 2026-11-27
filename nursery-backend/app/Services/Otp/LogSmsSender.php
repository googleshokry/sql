<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info("[SMS to {$phone}] {$message}");
    }
}
