<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Quotation;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * ترحيل المستندات: يحرّك المخزون وينشئ القيود المحاسبية آلياً.
 */
class Posting
{
    public static function post(Model $doc): void
    {
        if ($doc->isPosted()) {
            throw new BusinessException('المستند مُرحّل بالفعل.');
        }

        DB::transaction(function () use ($doc) {
            match (true) {
                $doc instanceof SalesInvoice => static::postSalesInvoice($doc),
                $doc instanceof SalesReturn => static::postSalesReturn($doc),
                $doc instanceof PurchaseInvoice => static::postPurchaseInvoice($doc),
                $doc instanceof PurchaseReturn => static::postPurchaseReturn($doc),
                $doc instanceof StockTransfer => static::postStockTransfer($doc),
                $doc instanceof StockAdjustment => static::postStockAdjustment($doc),
                $doc instanceof Payroll => static::postPayroll($doc),
                default => throw new BusinessException('هذا المستند لا يدعم الترحيل.'),
            };

            $doc->forceFill(['status' => 'posted', 'posted_at' => now()])->saveQuietly();

            if ($doc instanceof SalesReturn) {
                $doc->salesInvoice?->refreshPaymentStatus();
            }
            if ($doc instanceof PurchaseReturn) {
                $doc->purchaseInvoice?->refreshPaymentStatus();
            }
        });
    }

    public static function unpost(Model $doc): void
    {
        if (! $doc->isPosted()) {
            return;
        }

        DB::transaction(function () use ($doc) {
            if ($doc instanceof Payroll) {
                foreach ($doc->items as $item) {
                    static::restoreAdvances($item->employee, (float) $item->advance_deduction);
                }
            }
            if (($doc instanceof SalesReturn || $doc instanceof PurchaseReturn) && $doc->treasury_id) {
                Payment::query()->where('payable_type', $doc->getMorphClass())->where('payable_id', $doc->id)->get()->each->delete();
            }

            Inventory::reverse($doc);
            Accounting::clear($doc);
            $doc->forceFill(['status' => 'draft', 'posted_at' => null])->saveQuietly();

            if ($doc instanceof SalesReturn) {
                $doc->salesInvoice?->refreshPaymentStatus();
            }
            if ($doc instanceof PurchaseReturn) {
                $doc->purchaseInvoice?->refreshPaymentStatus();
            }
        });
    }

    protected static function warehouseId(Model $doc): int
    {
        return $doc->warehouse_id
            ?? Warehouse::query()->where('is_default', true)->value('id')
            ?? Warehouse::query()->value('id')
            ?? throw new BusinessException('يجب إنشاء مخزن أولاً.');
    }

    protected static function ensureItems(Model $doc): void
    {
        $doc->load('items.product');
        if ($doc->items->isEmpty()) {
            throw new BusinessException('لا يمكن ترحيل مستند بدون بنود.');
        }
    }

    protected static function postSalesInvoice(SalesInvoice $doc): void
    {
        static::ensureItems($doc);
        $wh = static::warehouseId($doc);
        $cost = 0;

        foreach ($doc->items as $item) {
            if ($item->product?->track_stock) {
                $unitCost = (float) $item->product->cost_price;
                $item->updateQuietly(['unit_cost' => $unitCost]);
                Inventory::move($item->product, $wh, -(float) $item->quantity, 'sale', $unitCost, $doc, $doc->date);
                $cost += $unitCost * (float) $item->quantity;
            }
        }

        $customer = $doc->customer;
        Accounting::sync($doc, $doc->date, "فاتورة مبيعات {$doc->number} ".($customer?->name ?? 'عميل نقدي'), [
            ['account_id' => Accounting::account('receivable')->id, 'debit' => $doc->total, 'party' => $customer],
            ['account_id' => Accounting::account('sales')->id, 'credit' => (float) $doc->subtotal - (float) $doc->discount_amount],
            ['account_id' => Accounting::account('shipping_revenue')->id, 'credit' => $doc->shipping],
            ['account_id' => Accounting::account('vat_output')->id, 'credit' => $doc->tax_amount],
            ['account_id' => Accounting::account('cogs')->id, 'debit' => round($cost, 2)],
            ['account_id' => Accounting::account('inventory')->id, 'credit' => round($cost, 2)],
        ]);
    }

    protected static function postSalesReturn(SalesReturn $doc): void
    {
        static::ensureItems($doc);
        $wh = static::warehouseId($doc);
        $cost = 0;

        foreach ($doc->items as $item) {
            if ($item->product?->track_stock) {
                $original = $doc->salesInvoice?->items()->where('product_id', $item->product_id)->value('unit_cost');
                $unitCost = (float) ($original ?: $item->product->cost_price);
                $item->updateQuietly(['unit_cost' => $unitCost]);
                Inventory::move($item->product, $wh, (float) $item->quantity, 'sale_return', $unitCost, $doc, $doc->date);
                $cost += $unitCost * (float) $item->quantity;
            }
        }

        $customer = $doc->customer;
        Accounting::sync($doc, $doc->date, "مرتجع مبيعات {$doc->number} ".($customer?->name ?? ''), [
            ['account_id' => Accounting::account('sales_returns')->id, 'debit' => $doc->netAmount()],
            ['account_id' => Accounting::account('vat_output')->id, 'debit' => $doc->tax_amount],
            ['account_id' => Accounting::account('receivable')->id, 'credit' => $doc->total, 'party' => $customer],
            ['account_id' => Accounting::account('inventory')->id, 'debit' => round($cost, 2)],
            ['account_id' => Accounting::account('cogs')->id, 'credit' => round($cost, 2)],
        ]);

        // رد المبلغ نقداً للعميل
        if ($doc->treasury_id) {
            Payment::create([
                'direction' => 'out',
                'date' => $doc->date,
                'treasury_id' => $doc->treasury_id,
                'party_type' => $customer?->getMorphClass(),
                'party_id' => $customer?->id,
                'payable_type' => $doc->getMorphClass(),
                'payable_id' => $doc->id,
                'amount' => $doc->total,
                'notes' => "رد قيمة مرتجع {$doc->number}",
            ]);
        }
    }

    protected static function postPurchaseInvoice(PurchaseInvoice $doc): void
    {
        static::ensureItems($doc);
        $wh = static::warehouseId($doc);
        $nonStock = 0;

        foreach ($doc->items as $item) {
            $unitCost = $item->netUnitPrice();
            $item->updateQuietly(['unit_cost' => $unitCost]);
            if ($item->product?->track_stock) {
                Inventory::move($item->product, $wh, (float) $item->quantity, 'purchase', $unitCost, $doc, $doc->date);
            } else {
                $nonStock += $unitCost * (float) $item->quantity;
            }
        }

        $net = (float) $doc->subtotal - (float) $doc->discount_amount;
        $supplier = $doc->supplier;
        Accounting::sync($doc, $doc->date, "فاتورة مشتريات {$doc->number} ".($supplier?->name ?? ''), [
            ['account_id' => Accounting::account('inventory')->id, 'debit' => round($net - $nonStock + (float) $doc->shipping, 2)],
            ['account_id' => Accounting::account('general_expenses')->id, 'debit' => round($nonStock, 2)],
            ['account_id' => Accounting::account('vat_input')->id, 'debit' => $doc->tax_amount],
            ['account_id' => Accounting::account('payable')->id, 'credit' => $doc->total, 'party' => $supplier],
        ]);
    }

    protected static function postPurchaseReturn(PurchaseReturn $doc): void
    {
        static::ensureItems($doc);
        $wh = static::warehouseId($doc);

        foreach ($doc->items as $item) {
            $unitCost = $item->netUnitPrice();
            $item->updateQuietly(['unit_cost' => $unitCost]);
            Inventory::move($item->product, $wh, -(float) $item->quantity, 'purchase_return', $unitCost, $doc, $doc->date);
        }

        $supplier = $doc->supplier;
        Accounting::sync($doc, $doc->date, "مرتجع مشتريات {$doc->number} ".($supplier?->name ?? ''), [
            ['account_id' => Accounting::account('payable')->id, 'debit' => $doc->total, 'party' => $supplier],
            ['account_id' => Accounting::account('inventory')->id, 'credit' => $doc->netAmount()],
            ['account_id' => Accounting::account('vat_input')->id, 'credit' => $doc->tax_amount],
        ]);

        if ($doc->treasury_id) {
            Payment::create([
                'direction' => 'in',
                'date' => $doc->date,
                'treasury_id' => $doc->treasury_id,
                'party_type' => $supplier?->getMorphClass(),
                'party_id' => $supplier?->id,
                'payable_type' => $doc->getMorphClass(),
                'payable_id' => $doc->id,
                'amount' => $doc->total,
                'notes' => "استرداد قيمة مرتجع مشتريات {$doc->number}",
            ]);
        }
    }

    protected static function postStockTransfer(StockTransfer $doc): void
    {
        static::ensureItems($doc);
        if ($doc->from_warehouse_id === $doc->to_warehouse_id) {
            throw new BusinessException('لا يمكن التحويل لنفس المخزن.');
        }
        foreach ($doc->items as $item) {
            $cost = (float) $item->product->cost_price;
            Inventory::move($item->product, $doc->from_warehouse_id, -(float) $item->quantity, 'transfer_out', $cost, $doc, $doc->date);
            Inventory::move($item->product, $doc->to_warehouse_id, (float) $item->quantity, 'transfer_in', $cost, $doc, $doc->date);
        }
    }

    protected static function postStockAdjustment(StockAdjustment $doc): void
    {
        static::ensureItems($doc);
        $value = 0;
        foreach ($doc->items as $item) {
            $system = $item->product->quantity($doc->warehouse_id);
            $item->update(['system_quantity' => $system]);
            $diff = (float) $item->actual_quantity - $system;
            $cost = (float) $item->product->cost_price;
            Inventory::move($item->product, $doc->warehouse_id, $diff, 'adjustment', $cost, $doc, $doc->date, 'تسوية جرد');
            $value += $diff * $cost;
        }

        $value = round($value, 2);
        Accounting::sync($doc, $doc->date, "تسوية جرد {$doc->number}", [
            ['account_id' => Accounting::account('inventory')->id, 'debit' => $value],
            ['account_id' => Accounting::account('inventory_adjustments')->id, 'credit' => $value],
        ]);
    }

    protected static function postPayroll(Payroll $doc): void
    {
        $doc->load('items.employee.advances');
        if ($doc->items->isEmpty()) {
            throw new BusinessException('مسير الرواتب فارغ.');
        }
        if (! $doc->treasury) {
            throw new BusinessException('اختر الخزينة التي سيتم صرف الرواتب منها.');
        }

        $lines = [];
        $gross = 0;
        $net = 0;
        foreach ($doc->items as $item) {
            $gross += (float) $item->basic_salary + (float) $item->allowances + (float) $item->commissions + (float) $item->bonuses - (float) $item->deductions;
            $net += (float) $item->net_salary;
            if ((float) $item->advance_deduction > 0) {
                $lines[] = ['account_id' => Accounting::account('employee_advances')->id, 'credit' => $item->advance_deduction, 'party' => $item->employee];
                static::deductAdvances($item->employee, (float) $item->advance_deduction);
            }
        }

        $lines[] = ['account_id' => Accounting::account('salaries')->id, 'debit' => round($gross, 2)];
        $lines[] = ['account_id' => $doc->treasury->account_id, 'credit' => round($net, 2)];

        Accounting::sync($doc, now()->parse($doc->month.'-01')->endOfMonth()->toDateString(), "مسير رواتب شهر {$doc->month}", $lines);
    }

    protected static function deductAdvances($employee, float $amount): void
    {
        foreach ($employee->advances()->whereColumn('deducted_amount', '<', 'amount')->orderBy('date')->get() as $advance) {
            if ($amount <= 0) {
                break;
            }
            $take = min($amount, $advance->remaining());
            $advance->updateQuietly(['deducted_amount' => (float) $advance->deducted_amount + $take]);
            $amount -= $take;
        }
    }

    protected static function restoreAdvances($employee, float $amount): void
    {
        foreach ($employee->advances()->where('deducted_amount', '>', 0)->orderByDesc('date')->get() as $advance) {
            if ($amount <= 0) {
                break;
            }
            $take = min($amount, (float) $advance->deducted_amount);
            $advance->updateQuietly(['deducted_amount' => (float) $advance->deducted_amount - $take]);
            $amount -= $take;
        }
    }

    // ===================== التحويل بين المستندات =====================

    public static function quotationToInvoice(Quotation $q): SalesInvoice
    {
        return DB::transaction(function () use ($q) {
            $invoice = SalesInvoice::create([
                'date' => now()->toDateString(),
                'customer_id' => $q->customer_id,
                'warehouse_id' => $q->warehouse_id,
                'quotation_id' => $q->id,
                'discount_type' => $q->discount_type,
                'discount_value' => $q->discount_value,
                'shipping' => $q->shipping,
                'notes' => $q->notes,
                'terms' => $q->terms,
            ]);
            static::copyItems($q, $invoice);
            $q->update(['status' => 'invoiced']);

            return $invoice;
        });
    }

    public static function purchaseOrderToInvoice(PurchaseOrder $po): PurchaseInvoice
    {
        return DB::transaction(function () use ($po) {
            $invoice = PurchaseInvoice::create([
                'date' => now()->toDateString(),
                'supplier_id' => $po->supplier_id,
                'warehouse_id' => $po->warehouse_id,
                'purchase_order_id' => $po->id,
                'discount_type' => $po->discount_type,
                'discount_value' => $po->discount_value,
                'shipping' => $po->shipping,
                'notes' => $po->notes,
            ]);
            static::copyItems($po, $invoice);
            $po->update(['status' => 'received']);

            return $invoice;
        });
    }

    public static function salesInvoiceToReturn(SalesInvoice $invoice): SalesReturn
    {
        return DB::transaction(function () use ($invoice) {
            $return = SalesReturn::create([
                'date' => now()->toDateString(),
                'customer_id' => $invoice->customer_id,
                'warehouse_id' => $invoice->warehouse_id,
                'sales_invoice_id' => $invoice->id,
                'discount_type' => $invoice->discount_type,
                'discount_value' => $invoice->discount_type === 'percent' ? $invoice->discount_value : 0,
            ]);
            static::copyItems($invoice, $return);

            return $return;
        });
    }

    public static function purchaseInvoiceToReturn(PurchaseInvoice $invoice): PurchaseReturn
    {
        return DB::transaction(function () use ($invoice) {
            $return = PurchaseReturn::create([
                'date' => now()->toDateString(),
                'supplier_id' => $invoice->supplier_id,
                'warehouse_id' => $invoice->warehouse_id,
                'purchase_invoice_id' => $invoice->id,
                'discount_type' => $invoice->discount_type,
                'discount_value' => $invoice->discount_type === 'percent' ? $invoice->discount_value : 0,
            ]);
            static::copyItems($invoice, $return);

            return $return;
        });
    }

    protected static function copyItems(Model $from, Model $to): void
    {
        foreach ($from->items as $item) {
            $to->items()->create($item->only(['product_id', 'description', 'quantity', 'unit_price', 'discount_percent', 'tax_rate']));
        }
        $to->refresh()->recalculate();
    }
}
