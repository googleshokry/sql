<?php

namespace App\Filament\Resources\JournalEntryResource\Pages;

use App\Filament\Resources\JournalEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJournalEntry extends CreateRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function beforeCreate(): void
    {
        JournalEntryResource::assertBalanced($this->data, $this);
    }

    protected function afterCreate(): void
    {
        $this->record->refreshTotal();
    }

    protected function getRedirectUrl(): string
    {
        return JournalEntryResource::getUrl('view', ['record' => $this->record]);
    }
}
