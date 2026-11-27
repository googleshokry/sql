<?php

namespace App\Filament\Resources\InstallmentPlanResource\Pages;

use App\Filament\Resources\InstallmentPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInstallmentPlan extends CreateRecord
{
    protected static string $resource = InstallmentPlanResource::class;

    protected function getRedirectUrl(): string
    {
        return InstallmentPlanResource::getUrl('view', ['record' => $this->record]);
    }
}
