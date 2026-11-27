<?php

namespace App\Filament\Resources\PayrollResource\Pages;

use App\Filament\Resources\PayrollResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPayroll extends EditRecord
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerate')->label('إعادة توليد البنود')->color('gray')->requiresConfirmation()
                ->action(function () {
                    $this->record->generateItems();
                    $this->fillForm();
                }),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->record->refreshTotal();
    }

    protected function getRedirectUrl(): string
    {
        return PayrollResource::getUrl('view', ['record' => $this->record]);
    }
}
