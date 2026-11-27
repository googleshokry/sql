<?php

namespace App\Filament\Resources\SalaryAdvanceResource\Pages;

use App\Filament\Resources\SalaryAdvanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSalaryAdvances extends ManageRecords
{
    protected static string $resource = SalaryAdvanceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
