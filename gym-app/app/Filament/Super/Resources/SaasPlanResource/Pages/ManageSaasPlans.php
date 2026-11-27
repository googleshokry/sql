<?php

namespace App\Filament\Super\Resources\SaasPlanResource\Pages;

use App\Filament\Super\Resources\SaasPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSaasPlans extends ManageRecords
{
    protected static string $resource = SaasPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
