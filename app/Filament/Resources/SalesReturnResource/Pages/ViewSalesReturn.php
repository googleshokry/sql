<?php

namespace App\Filament\Resources\SalesReturnResource\Pages;

use App\Filament\Actions\DocumentActions;
use App\Filament\Resources\SalesReturnResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesReturn extends ViewRecord
{
    protected static string $resource = SalesReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ...DocumentActions::forView(SalesReturnResource::class),
        ];
    }
}
