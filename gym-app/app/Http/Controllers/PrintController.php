<?php

namespace App\Http\Controllers;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Subscription;
use App\Support\Tenancy;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrintController extends Controller
{
    /** Only staff of the record's gym may print it; the gym becomes the active tenant. */
    private function enterGym(?Gym $gym): void
    {
        abort_unless($gym && auth()->user()?->canAccessTenant($gym), 403);
        Tenancy::set($gym);
    }

    public function card(Member $member)
    {
        $this->enterGym($member->gym);
        $barcode = (new BarcodeGeneratorSVG)->getBarcode($member->code, BarcodeGeneratorSVG::TYPE_CODE_128, 2, 50);

        return view('print.card', ['member' => $member->load('latestSubscription.plan'), 'barcode' => $barcode]);
    }

    public function receipt(Payment $payment)
    {
        $this->enterGym($payment->gym);
        return view('print.receipt', ['payment' => $payment->load(['member', 'subscription.plan', 'receiver'])]);
    }

    public function sale(Sale $sale)
    {
        $this->enterGym($sale->gym);
        return view('print.sale', ['sale' => $sale->load(['items.product', 'member', 'user'])]);
    }

    public function exportMembers(Gym $gym): StreamedResponse
    {
        $this->enterGym($gym);
        return response()->streamDownload(function () use ($gym) {
            Tenancy::set($gym);
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Arabic correctly
            fputcsv($out, [__('الكود'), __('الاسم'), __('الموبايل'), __('الرقم القومي'), __('النوع'), __('الباقة'), __('البداية'), __('النهاية'), __('الحالة'), __('المتبقي عليه'), __('تاريخ الانضمام')]);
            Member::with(['latestSubscription.plan'])->orderBy('id')->chunk(500, function ($members) use ($out) {
                foreach ($members as $m) {
                    $s = $m->latestSubscription;
                    fputcsv($out, [
                        $m->code, $m->name, $m->phone, $m->national_id, $m->gender === 'female' ? __('أنثى') : __('ذكر'),
                        $s?->plan?->name, $s?->start_date?->format('Y-m-d'), $s?->end_date?->format('Y-m-d'),
                        $s ? (Subscription::statuses()[$s->status] ?? $s->status) : __('بدون اشتراك'),
                        $m->balance, $m->joined_at?->format('Y-m-d'),
                    ]);
                }
            });
            fclose($out);
        }, 'members-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
