<?php

namespace App\Filament\Resources\CrmActivityResource\Pages;

use App\Filament\Resources\CrmActivityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCrmActivities extends ManageRecords
{
    protected static string $resource = CrmActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
