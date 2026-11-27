<?php

namespace App\Billing;

use App\Models\Central\Payment;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Paymob (Unified Checkout / Intention API)
 * https://developers.paymob.com
 */
class Paymob
{
    /** ترتيب الحقول اللي بيتحسب عليها الـ HMAC في إشعار المعاملة */
    private const HMAC_FIELDS = [
        'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id',
        'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
        'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan', 'source_data.sub_type',
        'source_data.type', 'success',
    ];

    public static function isConfigured(): bool
    {
        $c = config('tenancy.paymob');

        return filled($c['secret_key']) && filled($c['public_key']) && filled($c['hmac_secret']) && ! empty($c['integration_ids']);
    }

    /** ينشئ intention ويرجع رابط صفحة الدفع */
    public function checkoutUrl(Payment $payment, Tenant $tenant, string $email, string $name, ?string $phone): string
    {
        if (! self::isConfigured()) {
            throw new RuntimeException('Paymob is not configured');
        }
        $c = config('tenancy.paymob');
        $amountCents = (int) round((float) $payment->amount * 100);
        [$first, $last] = array_pad(explode(' ', trim($name), 2), 2, '-');

        $response = Http::withHeaders(['Authorization' => 'Token '.$c['secret_key']])
            ->acceptJson()
            ->timeout(20)
            ->post(rtrim($c['base_url'], '/').'/v1/intention/', [
                'amount' => $amountCents,
                'currency' => $payment->currency,
                'payment_methods' => $c['integration_ids'],
                'items' => [[
                    'name' => mb_substr(($payment->plan?->name_en ?? $payment->plan?->name ?? 'Subscription').' x'.$payment->months, 0, 50),
                    'amount' => $amountCents,
                    'description' => 'POS subscription - '.$tenant->slug,
                    'quantity' => 1,
                ]],
                'billing_data' => [
                    'first_name' => $first ?: '-',
                    'last_name' => $last ?: '-',
                    'email' => $email,
                    'phone_number' => $phone ?: '+201000000000',
                    'apartment' => 'NA', 'floor' => 'NA', 'street' => 'NA', 'building' => 'NA',
                    'city' => 'NA', 'state' => 'NA', 'country' => 'EG',
                ],
                'special_reference' => 'pay-'.$payment->id.'-'.now()->timestamp,
                'notification_url' => route('paymob.webhook'),
                'redirection_url' => route('paymob.return'),
            ]);

        if (! $response->successful() || ! $response->json('client_secret')) {
            throw new RuntimeException('Paymob intention failed: '.$response->status().' '.$response->body());
        }

        $payment->update([
            'gateway_order_id' => (string) $response->json('intention_order_id'),
            'reference' => (string) $response->json('id'),
        ]);

        return rtrim($c['base_url'], '/').'/unifiedcheckout/?'.http_build_query([
            'publicKey' => $c['public_key'],
            'clientSecret' => $response->json('client_secret'),
        ]);
    }

    /** التحقق من توقيع الإشعار (JSON object) */
    public function validWebhook(array $obj, ?string $hmac): bool
    {
        return $this->check(fn (string $field) => data_get($obj, $field), $hmac);
    }

    /** التحقق من رجوع العميل (query string: order بدل order.id و source_data_pan ...) */
    public function validRedirect(array $query, ?string $hmac): bool
    {
        return $this->check(fn (string $field) => $query[str_replace('.', '_', $field === 'order.id' ? 'order' : $field)] ?? null, $hmac);
    }

    private function check(callable $value, ?string $hmac): bool
    {
        $secret = config('tenancy.paymob.hmac_secret');
        if (blank($secret) || blank($hmac)) {
            return false;
        }

        $data = '';
        foreach (self::HMAC_FIELDS as $field) {
            $v = $value($field);
            $data .= is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
        }

        return hash_equals(hash_hmac('sha512', $data, $secret), strtolower($hmac));
    }

    /** يطبق نتيجة المعاملة على الدفعة (مرة واحدة) */
    public function apply(array $tx): ?Payment
    {
        $orderId = (string) data_get($tx, 'order.id', data_get($tx, 'order'));
        $payment = Payment::where('method', 'paymob')->where('gateway_order_id', $orderId)->first();
        if (! $payment) {
            return null;
        }

        $success = filter_var(data_get($tx, 'success'), FILTER_VALIDATE_BOOL);
        $pending = filter_var(data_get($tx, 'pending'), FILTER_VALIDATE_BOOL);
        $amountOk = (int) data_get($tx, 'amount_cents') === (int) round((float) $payment->amount * 100);

        if ($success && ! $pending && $amountOk) {
            $payment->markPaid(['gateway_transaction_id' => (string) data_get($tx, 'id'), 'payload' => $tx]);
        } elseif (! $success && ! $pending && $payment->status === 'pending') {
            $payment->update(['status' => 'failed', 'gateway_transaction_id' => (string) data_get($tx, 'id'), 'payload' => $tx]);
        }

        return $payment->fresh();
    }
}
