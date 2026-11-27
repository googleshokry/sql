<?php

namespace App\Filament\Resources\PurchaseReturnResource\Pages;

use App\Filament\Actions\DocumentActions;
use App\Filament\Resources\PurchaseReturnResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseReturn extends ViewRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ...DocumentActions::forView(PurchaseReturnResource::class),
        ];
    }
}
