<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Pages\Reports\PartyStatement;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\SupplierResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('statement')->label('كشف حساب')->icon(Heroicon::OutlinedDocumentChartBar)->color('info')
                ->url(fn () => PartyStatement::getUrl(['party' => 'supplier', 'id' => $this->record->id])),
            Action::make('payment')->label('سند صرف')->icon(Heroicon::OutlinedBanknotes)->color('warning')
                ->url(fn () => PaymentResource::getUrl('create', ['direction' => 'out', 'party_type' => 'supplier', 'party_id' => $this->record->id])),
            EditAction::make(),
        ];
    }
}
