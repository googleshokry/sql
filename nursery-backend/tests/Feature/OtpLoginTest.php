<?php

use App\Models\User;
use App\Services\Otp\OtpService;
use App\Services\Otp\SmsSender;

beforeEach(function () {
    $this->sms = new class implements SmsSender {
        public array $sent = [];

        public function send(string $phone, string $message): void
        {
            preg_match('/\d{6}/', $message, $m);
            $this->sent[$phone] = $m[0] ?? null;
        }
    };

    app()->instance(SmsSender::class, $this->sms);
});

it('normalizes Egyptian phone formats', function (string $input) {
    expect(OtpService::normalizePhone($input))->toBe('+201012345678');
})->with(['01012345678', '+201012345678', '00201012345678', '٠١٠١٢٣٤٥٦٧٨', '010 1234 5678']);

it('logs in a registered phone with the right code', function () {
    User::factory()->create(['phone' => '01012345678']);

    $this->postJson('/api/v1/auth/otp/request', ['phone' => '01012345678'])->assertOk();
    $code = $this->sms->sent['+201012345678'];

    $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+201012345678', 'code' => $code, 'device_name' => 'test'])
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'nurseries']]);
});

it('does not reveal or text unknown phones', function () {
    $this->postJson('/api/v1/auth/otp/request', ['phone' => '01099999999'])->assertOk();

    expect($this->sms->sent)->toBeEmpty();
});

it('rejects a wrong code and locks after too many attempts', function () {
    User::factory()->create(['phone' => '01012345678']);
    $this->postJson('/api/v1/auth/otp/request', ['phone' => '01012345678']);
    $code = $this->sms->sent['+201012345678'];

    foreach (range(1, OtpService::MAX_ATTEMPTS) as $_) {
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01012345678', 'code' => '000000', 'device_name' => 't'])
            ->assertUnprocessable();
    }

    // Even the right code fails once locked.
    $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01012345678', 'code' => $code, 'device_name' => 't'])
        ->assertUnprocessable();
});
