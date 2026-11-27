<?php

namespace App\Filament\Resources\PayrollResource\Pages;

use App\Filament\Actions\SimplePostActions;
use App\Filament\Resources\PayrollResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPayroll extends ViewRecord
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), ...SimplePostActions::make()];
    }
}
