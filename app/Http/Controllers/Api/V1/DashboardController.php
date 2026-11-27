<?php

namespace App\Http\Controllers\Api\V1;

use App\Filament\Pages\Reports\IncomeStatement;
use App\Http\Controllers\Controller;
use App\Http\Resources\SalesInvoiceResource;
use App\Models\Expense;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\Treasury;
use App\Services\Accounting;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $posted = fn () => SalesInvoice::query()->whereNotNull('posted_at');

        $chart = collect(range(6, 0))->map(fn (int $daysAgo) => [
            'date' => today()->subDays($daysAgo)->toDateString(),
            'total' => (float) $posted()->whereDate('date', today()->subDays($daysAgo))->sum('total'),
        ]);

        return response()->json([
            'sales_today' => (float) $posted()->whereDate('date', today())->sum('total'),
            'invoices_today' => $posted()->whereDate('date', today())->count(),
            'sales_month' => (float) $posted()->whereYear('date', now()->year)->whereMonth('date', now()->month)->sum('total'),
            'profit_month' => IncomeStatement::compute(now()->startOfMonth()->toDateString(), now()->toDateString())['net'],
            'expenses_month' => (float) Expense::query()->whereYear('date', now()->year)->whereMonth('date', now()->month)->sum('total'),
            'cash_balance' => round(Treasury::query()->with('account')->get()->sum(fn (Treasury $treasury) => $treasury->balance()), 2),
            'receivables' => Accounting::account('receivable')->balance(),
            'payables' => Accounting::account('payable')->balance(),
            'low_stock_count' => Product::query()->lowStock()->count(),
            'last_7_days' => $chart,
            'latest_invoices' => SalesInvoiceResource::collection($posted()->with('customer')->latest('id')->limit(5)->get()),
        ]);
    }
}
