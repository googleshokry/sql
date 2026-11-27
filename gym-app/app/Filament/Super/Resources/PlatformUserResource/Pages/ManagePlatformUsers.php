<?php

namespace App\Filament\Super\Resources\PlatformUserResource\Pages;

use App\Filament\Super\Resources\PlatformUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePlatformUsers extends ManageRecords
{
    protected static string $resource = PlatformUserResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
