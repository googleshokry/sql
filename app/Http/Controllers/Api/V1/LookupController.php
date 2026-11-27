<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Treasury;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;

/** Settings and dropdown lists the mobile app needs on start-up. */
class LookupController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $settings = Setting::values();

        return response()->json([
            'company' => [
                'name' => $settings['company_name'],
                'name_en' => $settings['company_name_en'],
                'tax_number' => $settings['tax_number'],
                'phone' => $settings['phone'],
                'logo_url' => $settings['logo'] ? asset('storage/'.$settings['logo']) : null,
            ],
            'currency' => __($settings['currency']),
            'default_tax_rate' => (float) $settings['default_tax_rate'],
            'warehouses' => Warehouse::query()->where('is_active', true)->get(['id', 'name', 'is_default']),
            'treasuries' => Treasury::query()->where('is_active', true)->get(['id', 'name', 'type', 'is_default']),
            'categories' => Category::query()->get(['id', 'name']),
            'expense_categories' => ExpenseCategory::query()->get(['id', 'name']),
            'payment_methods' => collect(Payment::METHODS)->map(fn (string $label, string $key) => ['key' => $key, 'label' => __($label)])->values(),
        ]);
    }
}
