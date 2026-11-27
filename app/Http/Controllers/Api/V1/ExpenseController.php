<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ExpenseResource::collection(
            Expense::query()->with(['category', 'treasury'])->latest('id')->paginate($request->integer('per_page', 20))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'treasury_id' => ['required', 'exists:treasuries,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $expense = Expense::create($data + ['date' => now()->toDateString(), 'tax_rate' => $data['tax_rate'] ?? 0]);

        return (new ExpenseResource($expense->load(['category', 'treasury'])))->response()->setStatusCode(201);
    }
}
