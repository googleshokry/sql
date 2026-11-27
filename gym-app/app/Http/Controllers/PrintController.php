<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Subscription;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrintController extends Controller
{
    public function card(Member $member)
    {
        $barcode = (new BarcodeGeneratorSVG)->getBarcode($member->code, BarcodeGeneratorSVG::TYPE_CODE_128, 2, 50);

        return view('print.card', ['member' => $member->load('latestSubscription.plan'), 'barcode' => $barcode]);
    }

    public function receipt(Payment $payment)
    {
        return view('print.receipt', ['payment' => $payment->load(['member', 'subscription.plan', 'receiver'])]);
    }

    public function sale(Sale $sale)
    {
        return view('print.sale', ['sale' => $sale->load(['items.product', 'member', 'user'])]);
    }

    public function exportMembers(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Arabic correctly
            fputcsv($out, ['الكود', 'الاسم', 'الموبايل', 'الرقم القومي', 'النوع', 'الباقة', 'البداية', 'النهاية', 'الحالة', 'المتبقي عليه', 'تاريخ الانضمام']);
            Member::with(['latestSubscription.plan'])->orderBy('id')->chunk(500, function ($members) use ($out) {
                foreach ($members as $m) {
                    $s = $m->latestSubscription;
                    fputcsv($out, [
                        $m->code, $m->name, $m->phone, $m->national_id, $m->gender === 'female' ? 'أنثى' : 'ذكر',
                        $s?->plan?->name, $s?->start_date?->format('Y-m-d'), $s?->end_date?->format('Y-m-d'),
                        $s ? (Subscription::STATUSES[$s->status] ?? $s->status) : 'بدون اشتراك',
                        $m->balance, $m->joined_at?->format('Y-m-d'),
                    ]);
                }
            });
            fclose($out);
        }, 'members-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
