<?php

namespace App\Filament\Platform\Resources\Nurseries\Pages;

use App\Actions\OnboardNursery;
use App\Filament\Platform\Resources\Nurseries\NurseryResource;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Creating a nursery also creates (or links) its first nursery_admin.
 */
class CreateNursery extends CreateRecord
{
    protected static string $resource = NurseryResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            ...NurseryResource::formComponents(),
            Section::make(__('app.first_admin'))->columns(2)->schema([
                TextInput::make('admin.name')->label(__('app.name'))->required(),
                TextInput::make('admin.email')->label(__('app.email'))->email()->required(),
                TextInput::make('admin.phone')->label(__('app.phone'))->tel(),
                TextInput::make('admin.password')->label(__('app.password'))->password()->revealable()
                    ->helperText(__('app.password_existing_user_hint'))->minLength(8),
            ]),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $admin = $data['admin'];
        unset($data['admin']);

        return app(OnboardNursery::class)($data, $admin);
    }
}
