<?php

namespace App\Filament\Resources\TreasuryTransferResource\Pages;

use App\Filament\Resources\TreasuryTransferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTreasuryTransfers extends ManageRecords
{
    protected static string $resource = TreasuryTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
