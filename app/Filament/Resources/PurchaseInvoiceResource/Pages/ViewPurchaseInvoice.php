<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Actions\DocumentActions;
use App\Filament\Resources\PurchaseInvoiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseInvoice extends ViewRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ...DocumentActions::forView(PurchaseInvoiceResource::class),
        ];
    }
}
