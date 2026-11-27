<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Actions\GymActions;
use App\Filament\Resources\MemberResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GymActions::checkIn(),
            GymActions::renew(),
            Action::make('card')->label('طباعة الكارنيه')->icon(Heroicon::OutlinedIdentification)->color('gray')
                ->url(fn () => route('print.card', $this->record), true),
            GymActions::whatsapp(),
            EditAction::make(),
        ];
    }
}
