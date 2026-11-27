<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\SalesInvoice;
use App\Support\DocumentTotals;
use Illuminate\Support\Facades\DB;

/**
 * Creates, posts and (optionally) collects a sales invoice in one step.
 * Shared by the web POS screen and the mobile API.
 */
class SalesCheckout
{
    /**
     * @param  array<int, array{product_id: int, quantity: float|int|string, unit_price: float|int|string, discount_percent?: float|int|string, tax_rate?: float|int|string, description?: ?string}>  $lines
     */
    public static function checkout(
        array $lines,
        ?int $customerId,
        int $warehouseId,
        ?int $treasuryId,
        string $method = 'cash',
        float $discount = 0,
        float $paid = 0,
        bool $isPos = true,
        ?string $notes = null,
    ): SalesInvoice {
        if (empty($lines)) {
            throw new BusinessException(__('السلة فارغة'));
        }

        $totals = DocumentTotals::calculate($lines, 'fixed', $discount);
        $paid = min(max($paid, 0), $totals['total']);

        if ($paid < $totals['total'] && ! $customerId) {
            throw new BusinessException(__('البيع الآجل يتطلب اختيار عميل'));
        }
        if ($paid > 0 && ! $treasuryId) {
            throw new BusinessException(__('اختر الخزينة'));
        }

        return DB::transaction(function () use ($lines, $customerId, $warehouseId, $treasuryId, $method, $discount, $paid, $isPos, $notes) {
            $invoice = SalesInvoice::create([
                'date' => now()->toDateString(),
                'customer_id' => $customerId,
                'warehouse_id' => $warehouseId,
                'is_pos' => $isPos,
                'payment_method' => $method,
                'discount_type' => 'fixed',
                'discount_value' => $discount,
                'notes' => $notes,
            ]);

            foreach ($lines as $line) {
                $invoice->items()->create([
                    'product_id' => $line['product_id'],
                    'description' => $line['description'] ?? null,
                    'quantity' => (float) $line['quantity'],
                    'unit_price' => (float) $line['unit_price'],
                    'discount_percent' => (float) ($line['discount_percent'] ?? 0),
                    'tax_rate' => (float) ($line['tax_rate'] ?? 0),
                ]);
            }

            $invoice->refresh();
            Posting::post($invoice);

            if ($paid > 0) {
                Payment::create([
                    'direction' => 'in',
                    'date' => $invoice->date,
                    'treasury_id' => $treasuryId,
                    'party_type' => $invoice->customer_id ? Customer::class : null,
                    'party_id' => $invoice->customer_id,
                    'payable_type' => SalesInvoice::class,
                    'payable_id' => $invoice->id,
                    'amount' => $paid,
                    'method' => $method,
                    'notes' => __('نقطة البيع - فاتورة').' '.$invoice->number,
                ]);
            }

            return $invoice->refresh();
        });
    }
}
