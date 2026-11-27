<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Actions\DocumentActions;
use App\Filament\Resources\QuotationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewQuotation extends ViewRecord
{
    protected static string $resource = QuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ...DocumentActions::forView(QuotationResource::class),
        ];
    }
}
