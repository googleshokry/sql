<?php

namespace App\Filament\Resources\StockAdjustmentResource\Pages;

use App\Filament\Actions\SimplePostActions;
use App\Filament\Resources\StockAdjustmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStockAdjustment extends ViewRecord
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), ...SimplePostActions::make()];
    }
}
