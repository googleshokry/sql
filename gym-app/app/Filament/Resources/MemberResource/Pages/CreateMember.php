<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Forms\SubscriptionFields;
use App\Filament\Resources\MemberResource;
use App\Services\SubscriptionService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    protected array $subscriptionData = [];

    public function form(Schema $schema): Schema
    {
        $schema = parent::form($schema);

        return $schema->components([
            ...$schema->getComponents(),
            Section::make('الاشتراك الأول (اختياري)')->columnSpanFull()->statePath('subscription')
                ->schema(SubscriptionFields::make(false)),
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->subscriptionData = $data['subscription'] ?? [];
        unset($data['subscription']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (filled($this->subscriptionData['plan_id'] ?? null)) {
            SubscriptionService::create($this->record, $this->subscriptionData);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
