<?php

namespace App\Filament\Widgets;

use App\Models\CrmActivity;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingActivities extends TableWidget
{
    protected static ?string $heading = 'مواعيد ومتابعات العملاء القادمة';

    protected static ?int $sort = 6;

    public function table(Table $table): Table
    {
        return $table
            ->query(CrmActivity::query()->with('customer')->where('status', 'pending')->orderBy('scheduled_at'))
            ->paginated([5])
            ->columns([
                TextColumn::make('scheduled_at')->label('الموعد')->dateTime('Y-m-d H:i'),
                TextColumn::make('customer.name')->label('العميل'),
                TextColumn::make('subject')->label('الموضوع')->limit(30),
            ])
            ->emptyStateHeading('لا توجد متابعات');
    }
}
