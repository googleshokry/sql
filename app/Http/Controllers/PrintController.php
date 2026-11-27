<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Quotation;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Setting;
use App\Support\Tenancy;
use App\Support\Zatca;

class PrintController extends Controller
{
    protected const DOCUMENTS = [
        'sales-invoice' => [SalesInvoice::class, 'فاتورة ضريبية', 'Tax Invoice'],
        'quotation' => [Quotation::class, 'عرض سعر', 'Quotation'],
        'sales-return' => [SalesReturn::class, 'إشعار دائن (مرتجع مبيعات)', 'Credit Note'],
        'purchase-order' => [PurchaseOrder::class, 'أمر شراء', 'Purchase Order'],
        'purchase-invoice' => [PurchaseInvoice::class, 'فاتورة مشتريات', 'Purchase Invoice'],
        'purchase-return' => [PurchaseReturn::class, 'مرتجع مشتريات', 'Debit Note'],
    ];

    public function document(string $type, int $id)
    {
        // signed links (mobile app) carry the company; the signature prevents tampering
        if (request()->routeIs('print.signed')) {
            Tenancy::set((int) request()->query('company'));
        }

        $settings = Setting::values();

        if ($type === 'payment') {
            $payment = Payment::with(['party', 'treasury', 'payable', 'account'])->findOrFail($id);

            return view('print.payment', compact('payment', 'settings'));
        }

        abort_unless(isset(self::DOCUMENTS[$type]), 404);
        [$model, $title, $titleEn] = self::DOCUMENTS[$type];
        $title = __($title);
        $doc = $model::with(['items.product.unit', 'branch', 'warehouse'])->findOrFail($id);
        $party = $doc->party();

        $qr = null;
        if ($type === 'sales-invoice' && ($settings['zatca_qr'] ?? '1') === '1') {
            if (! $party?->tax_number) {
                $title = __('فاتورة ضريبية مبسطة');
                $titleEn = 'Simplified Tax Invoice';
            }
            $qr = Zatca::qrDataUri(Zatca::tlv(
                (string) $settings['company_name'],
                (string) $settings['tax_number'],
                $doc->created_at->toIso8601String(),
                number_format((float) $doc->total, 2, '.', ''),
                number_format((float) $doc->tax_amount, 2, '.', ''),
            ));
        }

        $view = request('format') === 'thermal' ? 'print.thermal' : 'print.document';

        return view($view, compact('doc', 'party', 'title', 'titleEn', 'settings', 'qr', 'type'));
    }

    public function barcodes()
    {
        $ids = array_filter(explode(',', (string) request('ids')));
        $products = Product::query()->whereIn('id', $ids)->get();
        $copies = max((int) request('copies', 1), 1);

        return view('print.barcodes', compact('products', 'copies'));
    }
}
