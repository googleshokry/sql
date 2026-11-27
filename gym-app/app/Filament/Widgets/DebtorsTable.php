<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\GymActions;
use App\Filament\Resources\MemberResource;
use App\Models\Setting;
use App\Models\Subscription;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class DebtorsTable extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';


    public function table(Table $table): Table
    {
        $cur = ' '.Setting::currency();

        return $table
            ->heading(__('مبالغ متبقية على المشتركين'))
            ->query(fn () => Subscription::query()->with(['member', 'plan'])->withSum('payments', 'amount')
                ->where('status', '!=', 'cancelled')
                ->whereRaw('total > (select coalesce(sum(amount),0) from payments where payments.subscription_id = subscriptions.id)')
                ->latest('start_date'))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading(__('لا توجد مديونيات'))
            ->columns([
                TextColumn::make('member.name')->label(__('المشترك'))->weight('bold')
                    ->url(fn (Subscription $r) => MemberResource::getUrl('view', ['record' => $r->member_id])),
                TextColumn::make('member.phone')->label(__('الموبايل')),
                TextColumn::make('plan.name')->label(__('الباقة')),
                TextColumn::make('total')->label(__('الإجمالي'))->numeric(2, locale: 'en')->suffix($cur),
                TextColumn::make('paid_amount')->label(__('المدفوع'))->numeric(2, locale: 'en')->suffix($cur),
                TextColumn::make('remaining_amount')->label(__('المتبقي'))->numeric(2, locale: 'en')->suffix($cur)->color('danger')->weight('bold'),
            ])
            ->recordActions([GymActions::pay()]);
    }
}
