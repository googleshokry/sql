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

    protected static ?string $heading = 'مبالغ متبقية على المشتركين';

    public function table(Table $table): Table
    {
        $cur = ' '.Setting::currency();

        return $table
            ->query(fn () => Subscription::query()->with(['member', 'plan'])->withSum('payments', 'amount')
                ->where('status', '!=', 'cancelled')
                ->whereRaw('total > (select coalesce(sum(amount),0) from payments where payments.subscription_id = subscriptions.id)')
                ->latest('start_date'))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('لا توجد مديونيات')
            ->columns([
                TextColumn::make('member.name')->label('المشترك')->weight('bold')
                    ->url(fn (Subscription $r) => MemberResource::getUrl('view', ['record' => $r->member_id])),
                TextColumn::make('member.phone')->label('الموبايل'),
                TextColumn::make('plan.name')->label('الباقة'),
                TextColumn::make('total')->label('الإجمالي')->numeric(2, locale: 'en')->suffix($cur),
                TextColumn::make('paid_amount')->label('المدفوع')->numeric(2, locale: 'en')->suffix($cur),
                TextColumn::make('remaining_amount')->label('المتبقي')->numeric(2, locale: 'en')->suffix($cur)->color('danger')->weight('bold'),
            ])
            ->recordActions([GymActions::pay()]);
    }
}
