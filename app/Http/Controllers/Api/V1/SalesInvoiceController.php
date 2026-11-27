<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\SalesInvoiceResource;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\SalesInvoice;
use App\Services\SalesCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class SalesInvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $invoices = SalesInvoice::query()
            ->with('customer')
            ->when($request->query('search'), fn ($query, string $term) => $query->where(fn ($q) => $q
                ->where('number', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"))))
            ->when($request->query('payment_status'), fn ($query, string $status) => $query->where('payment_status', $status))
            ->when($request->query('customer_id'), fn ($query, $id) => $query->where('customer_id', $id))
            ->when($request->query('from'), fn ($query, $date) => $query->whereDate('date', '>=', $date))
            ->when($request->query('to'), fn ($query, $date) => $query->whereDate('date', '<=', $date))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return SalesInvoiceResource::collection($invoices);
    }

    public function show(SalesInvoice $salesInvoice): SalesInvoiceResource
    {
        return new SalesInvoiceResource($salesInvoice->load(['customer', 'items']));
    }

    /** Create, post and optionally collect a sales invoice (mobile POS / field sales). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'treasury_id' => ['nullable', 'exists:treasuries,id'],
            'payment_method' => ['nullable', Rule::in(array_keys(Payment::METHODS))],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $invoice = SalesCheckout::checkout(
                lines: $data['items'],
                customerId: $data['customer_id'] ?? null,
                warehouseId: (int) $data['warehouse_id'],
                treasuryId: $data['treasury_id'] ?? null,
                method: $data['payment_method'] ?? 'cash',
                discount: (float) ($data['discount'] ?? 0),
                paid: (float) ($data['paid'] ?? 0),
                isPos: false,
                notes: $data['notes'] ?? null,
            );
        } catch (BusinessException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new SalesInvoiceResource($invoice->load(['customer', 'items'])))->response()->setStatusCode(201);
    }

    public function addPayment(Request $request, SalesInvoice $salesInvoice): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.$salesInvoice->remainingAmount()],
            'treasury_id' => ['required', 'exists:treasuries,id'],
            'method' => ['nullable', Rule::in(array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        if (! $salesInvoice->isPosted()) {
            return response()->json(['message' => __('الفاتورة غير مرحّلة')], 422);
        }

        $payment = Payment::create([
            'direction' => 'in',
            'date' => now()->toDateString(),
            'treasury_id' => $data['treasury_id'],
            'party_type' => $salesInvoice->customer_id ? Customer::class : null,
            'party_id' => $salesInvoice->customer_id,
            'payable_type' => SalesInvoice::class,
            'payable_id' => $salesInvoice->id,
            'amount' => $data['amount'],
            'method' => $data['method'] ?? 'cash',
            'reference' => $data['reference'] ?? null,
            'notes' => __('تحصيل من فاتورة').' '.$salesInvoice->number,
        ]);

        return (new PaymentResource($payment->load(['treasury', 'party', 'payable'])))->response()->setStatusCode(201);
    }
}
