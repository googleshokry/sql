<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make('receipt')->label('سند قبض جديد')->color('success')
                ->url(fn () => PaymentResource::getUrl('create', ['direction' => 'in'])),
            CreateAction::make('disbursement')->label('سند صرف جديد')->color('danger')
                ->url(fn () => PaymentResource::getUrl('create', ['direction' => 'out'])),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل'),
            'in' => Tab::make('سندات القبض')->modifyQueryUsing(fn (Builder $query) => $query->where('direction', 'in')),
            'out' => Tab::make('سندات الصرف')->modifyQueryUsing(fn (Builder $query) => $query->where('direction', 'out')),
        ];
    }
}
