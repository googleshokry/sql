<?php

namespace App\Filament\Resources\InstallmentPlanResource\Pages;

use App\Filament\Resources\InstallmentPlanResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewInstallmentPlan extends ViewRecord
{
    protected static string $resource = InstallmentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')->label(__('إلغاء الخطة'))->color('danger')->requiresConfirmation()
                ->visible(fn () => $this->record->status === 'active')
                ->action(fn () => $this->record->update(['status' => 'cancelled'])),
        ];
    }
}
