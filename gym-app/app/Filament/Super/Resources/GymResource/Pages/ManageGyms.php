<?php

namespace App\Filament\Super\Resources\GymResource\Pages;

use App\Filament\Super\Resources\GymResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGyms extends ManageRecords
{
    protected static string $resource = GymResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data, CreateAction $action) => GymResource::createWithOwner(
                $data,
                $action->getSchema()->getRawState(),
            )),
        ];
    }
}
