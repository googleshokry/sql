<?php

namespace App\Http\Controllers;

use App\Billing\Paymob;
use App\Models\Central\Payment;
use App\Models\Central\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class PaymobController extends Controller
{
    public function __construct(private Paymob $paymob) {}

    /** المدير يختار الباقة والمدة ← يتحول لصفحة دفع Paymob */
    public function checkout(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless(Paymob::isConfigured(), 404);

        $data = $request->validate([
            'plan_id' => ['required', Rule::exists(Plan::class, 'id')->where('is_active', true)],
            'months' => ['required', Rule::in([1, 3, 6, 12])],
        ]);
        $plan = Plan::findOrFail($data['plan_id']);
        $tenant = tenant();

        $payment = $tenant->payments()->create([
            'plan_id' => $plan->id,
            'months' => (int) $data['months'],
            'amount' => $plan->priceFor((int) $data['months']),
            'method' => 'paymob',
            'status' => 'pending',
        ]);

        try {
            $url = $this->paymob->checkoutUrl($payment, $tenant, $request->user()->email, $request->user()->name, $tenant->owner_phone);
        } catch (Throwable $e) {
            Log::error('Paymob checkout failed', ['payment' => $payment->id, 'error' => $e->getMessage()]);
            $payment->update(['status' => 'failed', 'notes' => mb_substr($e->getMessage(), 0, 500)]);

            return redirect()->route('filament.app.pages.billing')->with('billing_error', __('تعذر فتح صفحة الدفع، حاول مرة أخرى.'));
        }

        return redirect()->away($url);
    }

    /** إشعار Paymob (server-to-server) */
    public function webhook(Request $request)
    {
        $obj = $request->input('obj', []);
        if (! is_array($obj) || ! $this->paymob->validWebhook($obj, $request->query('hmac'))) {
            Log::warning('Paymob webhook with invalid HMAC');

            return response()->json(['ok' => false], 403);
        }

        $this->paymob->apply($obj);

        return response()->json(['ok' => true]);
    }

    /** العميل راجع من صفحة الدفع */
    public function return(Request $request)
    {
        $query = $request->query();
        $valid = $this->paymob->validRedirect($query, $request->query('hmac'));
        $payment = $valid ? $this->paymob->apply($query) : null;
        $payment ??= Payment::where('method', 'paymob')->where('gateway_order_id', (string) $request->query('order'))->first();

        if (! $payment) {
            return redirect()->route('landing');
        }

        $status = $payment->status === 'paid' ? 'paid' : ($payment->status === 'failed' ? 'failed' : 'pending');

        return redirect($payment->tenant->url('billing').'?payment='.$status);
    }
}
