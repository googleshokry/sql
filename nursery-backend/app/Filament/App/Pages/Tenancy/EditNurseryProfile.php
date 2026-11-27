<?php

namespace App\Filament\App\Pages\Tenancy;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditNurseryProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return __('app.nursery_profile');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('app.name'))->columns(2)->schema([
                TextInput::make('name.ar')->label(__('app.name_ar'))->required()->maxLength(120),
                TextInput::make('name.en')->label(__('app.name_en'))->required()->maxLength(120),
            ]),
            FileUpload::make('logo_path')->label(__('app.logo'))->image()->directory('logos'),
            Select::make('default_locale')->label(__('app.default_language'))
                ->options(['ar' => 'العربية', 'en' => 'English'])->required(),
            Select::make('timezone')->label(__('app.timezone'))
                ->options(fn () => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                ->searchable()->required(),
        ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load both translations into name.ar / name.en
        $data['name'] = $this->tenant->getTranslations('name');

        return $data;
    }
}
