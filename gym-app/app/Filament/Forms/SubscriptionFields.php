<?php

namespace App\Filament\Forms;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Trainer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Carbon;

class SubscriptionFields
{
    /**
     * Fields used whenever a new subscription is sold (member create, renew, subscriptions page).
     *
     * @param  bool  $required  When false the plan is optional (e.g. adding a member without a subscription).
     */
    public static function make(bool $required = true, ?Carbon $defaultStart = null): array
    {
        $currency = Setting::currency();

        return [
            Grid::make(3)->columnSpanFull()->schema([
                Select::make('plan_id')->label(__('الباقة'))
                    ->options(fn () => Plan::where('is_active', true)->get()
                        ->mapWithKeys(fn (Plan $p) => [$p->id => __(':name - :days يوم - :price', ['name' => $p->name, 'days' => $p->duration_days, 'price' => number_format((float) $p->price).' '.$currency])]))
                    ->required($required)->searchable()->live()
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        $plan = $state ? Plan::find($state) : null;
                        $set('price', $plan?->price);
                        $set('paid_amount', $plan?->price);
                        $set('discount', 0);
                    })->columnSpan(2),
                DatePicker::make('start_date')->label(__('تاريخ البداية'))
                    ->default(($defaultStart ?? today())->toDateString())->required($required)->live(),
                TextInput::make('price')->label(__('السعر'))->numeric()->minValue(0)->suffix($currency)->live(onBlur: true)
                    ->required($required)->visible(fn (Get $get) => filled($get('plan_id'))),
                TextInput::make('discount')->label(__('الخصم'))->numeric()->minValue(0)->default(0)->suffix($currency)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => $set('paid_amount', max(0, (float) $get('price') - (float) $get('discount'))))
                    ->visible(fn (Get $get) => filled($get('plan_id'))),
                TextEntry::make('summary')->label(__('الإجمالي / تاريخ الانتهاء'))
                    ->state(function (Get $get) use ($currency) {
                        $plan = Plan::find($get('plan_id'));
                        if (! $plan) {
                            return '-';
                        }
                        $total = max(0, (float) $get('price') - (float) $get('discount'));
                        $end = Carbon::parse($get('start_date') ?: today())->addDays($plan->duration_days - 1)->format('Y-m-d');

                        return __(':total — ينتهي :date', ['total' => number_format($total, 2).' '.$currency, 'date' => $end]);
                    })->visible(fn (Get $get) => filled($get('plan_id'))),
                TextInput::make('paid_amount')->label(__('المدفوع الآن'))->numeric()->minValue(0)->suffix($currency)
                    ->visible(fn (Get $get) => filled($get('plan_id'))),
                Select::make('method')->label(__('طريقة الدفع'))->options(Payment::methods())->default('cash')
                    ->visible(fn (Get $get) => filled($get('plan_id'))),
                Select::make('trainer_id')->label(__('المدرب'))
                    ->options(fn () => Trainer::where('is_active', true)->pluck('name', 'id'))->searchable()
                    ->visible(fn (Get $get) => filled($get('plan_id'))),
                Textarea::make('notes')->label(__('ملاحظات الاشتراك'))->columnSpanFull()
                    ->visible(fn (Get $get) => filled($get('plan_id'))),
            ]),
        ];
    }
}
