<?php

namespace App\Filament\Resources\StockTransferResource\Pages;

use App\Filament\Actions\SimplePostActions;
use App\Filament\Resources\StockTransferResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStockTransfer extends ViewRecord
{
    protected static string $resource = StockTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), ...SimplePostActions::make()];
    }
}
