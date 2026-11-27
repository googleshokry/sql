<?php

namespace App\Filament\Resources\PayrollResource\Pages;

use App\Filament\Resources\PayrollResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayroll extends CreateRecord
{
    protected static string $resource = PayrollResource::class;

    /** توليد بنود المسير تلقائياً من بيانات الموظفين */
    protected function afterCreate(): void
    {
        $this->record->generateItems();
    }

    protected function getRedirectUrl(): string
    {
        return PayrollResource::getUrl('edit', ['record' => $this->record]);
    }
}
