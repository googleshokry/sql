<?php

namespace App\Filament\Pages;

use App\Billing\Paymob;
use App\Filament\Concerns\AdminOnly;
use App\Models\Central\Payment;
use App\Models\Central\Plan;
use App\Tenancy\PlanLimits;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** اشتراك المطعم: الباقة، الاستخدام، التجديد (Paymob أو دفع يدوي) */
class Billing extends Page
{
    use AdminOnly;

    protected string $view = 'filament.pages.billing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = \App\Filament\NavGroup::Settings;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('الاشتراك');
    }

    public function getTitle(): string
    {
        return __('الاشتراك');
    }

    public static function getNavigationBadge(): ?string
    {
        $t = tenant();
        if (! $t) {
            return null;
        }

        return $t->canOperate() && $t->daysLeft() > 7 ? null : (string) $t->daysLeft();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    protected function getViewData(): array
    {
        $tenant = tenant();

        return [
            'tenant' => $tenant,
            'state' => $tenant->accessState(),
            'plans' => Plan::where('is_active', true)->orderBy('sort')->get(),
            'usage' => collect(Plan::LIMITS)->mapWithKeys(fn ($k) => [$k => [
                'used' => PlanLimits::usage($k),
                'limit' => PlanLimits::limit($k),
            ]])->all(),
            'payments' => $tenant->payments()->with('plan')->latest('id')->limit(20)->get(),
            'paymob' => Paymob::isConfigured(),
            'instructions' => config('tenancy.manual_payment_instructions'),
            'flash' => request()->query('payment'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manual')
                ->label(__('أبلغ عن دفع يدوي'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->modalDescription(fn () => __('حوّل المبلغ ثم سجل رقم العملية هنا، وإدارة المنصة هتفعّل الاشتراك بعد المراجعة.').' '.config('tenancy.manual_payment_instructions'))
                ->schema([
                    Select::make('plan_id')->label(__('الباقة'))->required()
                        ->options(fn () => Plan::where('is_active', true)->orderBy('sort')->get()->mapWithKeys(fn ($p) => [$p->id => $p->localized_name]))
                        ->default(fn () => tenant()->plan_id),
                    Select::make('months')->label(__('المدة'))->required()->default(1)
                        ->options([1 => __('شهر'), 3 => __('3 شهور'), 6 => __('6 شهور'), 12 => __('سنة')]),
                    Select::make('method')->label(__('طريقة الدفع'))->required()->default('instapay')
                        ->options(collect(['instapay', 'wallet', 'bank', 'cash'])->mapWithKeys(fn ($m) => [$m => Payment::methodLabel($m)])),
                    TextInput::make('reference')->label(__('رقم العملية / المرجع'))->required()->maxLength(100),
                ])
                ->action(function (array $data) {
                    $plan = Plan::findOrFail($data['plan_id']);
                    tenant()->payments()->create([
                        'plan_id' => $plan->id,
                        'months' => (int) $data['months'],
                        'amount' => $plan->priceFor((int) $data['months']),
                        'method' => $data['method'],
                        'reference' => $data['reference'],
                        'status' => 'pending',
                    ]);
                    Notification::make()->success()->title(__('تم إرسال طلب التفعيل، هيتراجع في أقرب وقت'))->send();
                }),
        ];
    }
}
