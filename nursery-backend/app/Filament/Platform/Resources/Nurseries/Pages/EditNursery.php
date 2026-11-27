<?php

namespace App\Filament\Platform\Resources\Nurseries\Pages;

use App\Filament\Platform\Resources\Nurseries\NurseryResource;
use Filament\Resources\Pages\EditRecord;

class EditNursery extends EditRecord
{
    protected static string $resource = NurseryResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['name'] = $this->record->getTranslations('name');

        return $data;
    }
}
