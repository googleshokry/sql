<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SalesInvoiceResource;
use App\Filament\Support\Labels;
use App\Models\SalesInvoice;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestInvoices extends TableWidget
{
    protected static ?string $heading = 'أحدث فواتير المبيعات';

    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(SalesInvoice::query()->with('customer')->latest('id')->limit(8))
            ->paginated(false)
            ->recordUrl(fn (SalesInvoice $record) => SalesInvoiceResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('number')->label('الرقم'),
                TextColumn::make('customer.name')->label('العميل')->placeholder('نقدي'),
                TextColumn::make('total')->label('الإجمالي')->numeric(2),
                TextColumn::make('payment_status')->label('السداد')->badge()
                    ->formatStateUsing(fn ($state) => Labels::PAYMENT_STATUS[$state] ?? $state)
                    ->color(fn ($state) => Labels::PAYMENT_STATUS_COLORS[$state] ?? 'gray'),
            ]);
    }
}
