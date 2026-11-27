<?php

namespace App\Filament\Resources\TreasuryResource\Pages;

use App\Filament\Resources\TreasuryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTreasuries extends ManageRecords
{
    protected static string $resource = TreasuryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
