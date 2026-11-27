<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Actions\DocumentActions;
use App\Filament\Resources\PurchaseOrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ...DocumentActions::forView(PurchaseOrderResource::class),
        ];
    }
}
