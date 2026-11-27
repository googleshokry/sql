<?php

namespace App\Filament\Pages;

use App\Models\Gym;
use App\Models\GymInvoice;
use App\Models\SaasPlan;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/** The gym's own SaaS subscription: status, plan choice and invoices. */
class Billing extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = \App\Enums\NavGroup::Admin;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'billing';

    protected string $view = 'filament.pages.billing';

    public string $cycle = 'monthly';

    public static function getNavigationLabel(): string
    {
        return __('الاشتراك والفواتير');
    }

    public function getTitle(): string
    {
        return __('اشتراك الجيم في النظام');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    public function getGymProperty(): Gym
    {
        return Filament::getTenant()->fresh(['saasPlan']);
    }

    public function getPlansProperty(): Collection
    {
        return SaasPlan::where('is_active', true)->orderBy('sort')->get();
    }

    public function getInvoicesProperty(): Collection
    {
        return GymInvoice::where('gym_id', $this->gym->id)->latest()->limit(24)->get();
    }

    public function getUsageProperty(): array
    {
        $gym = $this->gym;

        return [
            'members' => $gym->members()->count(),
            'staff' => $gym->users()->count(),
            'branches' => Gym::where('owner_id', $gym->owner_id)->count(),
        ];
    }

    /** Request a subscription: issues an unpaid invoice the SaaS owner confirms after payment. */
    public function choose(int $planId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $plan = SaasPlan::where('is_active', true)->findOrFail($planId);
        $gym = $this->gym;

        GymInvoice::where('gym_id', $gym->id)->where('status', 'unpaid')->update(['status' => 'void']);
        $gym->forceFill(['saas_plan_id' => $plan->id, 'billing_cycle' => $this->cycle])->save();
        $invoice = GymInvoice::issueFor($gym, $plan, $this->cycle);

        Notification::make()
            ->title(__('تم إصدار الفاتورة :number', ['number' => $invoice->number]))
            ->body(__('سيتم تفعيل الاشتراك فور تأكيد الدفع.'))
            ->success()->send();
    }
}
