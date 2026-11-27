<?php

namespace App\Http\Controllers\Api\V1;

use App\Filament\Pages\Reports\PartyStatement;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $customers = Customer::query()
            ->where('is_active', true)
            ->when($request->query('search'), fn ($query, string $term) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 30));

        return CustomerResource::collection($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'price_list' => ['nullable', Rule::in(['retail', 'wholesale'])],
        ]);

        return (new CustomerResource(Customer::create($data)))->response()->setStatusCode(201);
    }

    public function show(Customer $customer): CustomerResource
    {
        return new CustomerResource($customer);
    }

    /** Account statement with running balance. */
    public function statement(Request $request, Customer $customer): JsonResponse
    {
        $report = new PartyStatement;
        $report->party = 'customer';
        $report->id = $customer->id;
        $report->from = $request->query('from', now()->startOfYear()->toDateString());
        $report->to = $request->query('to', now()->toDateString());

        $data = $report->statementData();

        return response()->json([
            'customer' => new CustomerResource($customer),
            'from' => $report->from,
            'to' => $report->to,
            'opening' => round($data['opening'], 2),
            'closing' => round($data['closing'], 2),
            'total_debit' => round((float) $data['totals'][0], 2),
            'total_credit' => round((float) $data['totals'][1], 2),
            'rows' => collect($data['rows'])->map(fn (array $row) => [
                'date' => Carbon::parse($row['date'])->toDateString(),
                'number' => $row['number'],
                'description' => $row['description'],
                'debit' => round($row['debit'], 2),
                'credit' => round($row['credit'], 2),
                'balance' => round($row['balance'], 2),
            ]),
        ]);
    }
}
