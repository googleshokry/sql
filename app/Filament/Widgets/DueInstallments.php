<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InstallmentPlanResource;
use App\Models\Installment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class DueInstallments extends TableWidget
{
    protected static ?string $heading = 'أقساط مستحقة خلال 7 أيام أو متأخرة';

    protected static ?int $sort = 5;

    public function table(Table $table): Table
    {
        return $table
            ->query(Installment::query()->with('plan.customer')
                ->whereColumn('paid_amount', '<', 'amount')
                ->whereDate('due_date', '<=', now()->addDays(7))
                ->whereHas('plan', fn ($q) => $q->where('status', 'active'))
                ->orderBy('due_date'))
            ->paginated([5])
            ->recordUrl(fn (Installment $record) => InstallmentPlanResource::getUrl('view', ['record' => $record->installment_plan_id]))
            ->columns([
                TextColumn::make('plan.customer.name')->label(__('العميل')),
                TextColumn::make('due_date')->label(__('الاستحقاق'))->date('Y-m-d')->color(fn ($record) => $record->isOverdue() ? 'danger' : 'warning'),
                TextColumn::make('amount')->label(__('القيمة'))->numeric(2),
            ])
            ->emptyStateHeading(__('لا توجد أقساط مستحقة'));
    }
}
