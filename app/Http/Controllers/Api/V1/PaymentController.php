<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Customer;
use App\Models\Payment;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->with(['treasury', 'party', 'payable'])
            ->when($request->query('direction'), fn ($query, string $direction) => $query->where('direction', $direction))
            ->when($request->query('customer_id'), fn ($query, $id) => $query->where('party_type', Customer::class)->where('party_id', $id))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return PaymentResource::collection($payments);
    }

    /** Receipt voucher (cash collected from a customer on account). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', TenantRule::exists('customers')],
            'treasury_id' => ['required', TenantRule::exists('treasuries')],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['nullable', Rule::in(array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = Payment::create([
            'direction' => 'in',
            'date' => now()->toDateString(),
            'treasury_id' => $data['treasury_id'],
            'party_type' => Customer::class,
            'party_id' => $data['customer_id'],
            'amount' => $data['amount'],
            'method' => $data['method'] ?? 'cash',
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? __('سند قبض من تطبيق الجوال'),
        ]);

        return (new PaymentResource($payment->load(['treasury', 'party'])))->response()->setStatusCode(201);
    }
}
