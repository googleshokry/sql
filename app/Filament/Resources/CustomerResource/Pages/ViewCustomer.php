<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Pages\Reports\PartyStatement;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\PaymentResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('statement')->label('كشف حساب')->icon(Heroicon::OutlinedDocumentChartBar)->color('info')
                ->url(fn () => PartyStatement::getUrl(['party' => 'customer', 'id' => $this->record->id])),
            Action::make('payment')->label('سند قبض')->icon(Heroicon::OutlinedBanknotes)->color('warning')
                ->url(fn () => PaymentResource::getUrl('create', ['direction' => 'in', 'party_type' => 'customer', 'party_id' => $this->record->id])),
            EditAction::make(),
        ];
    }
}
