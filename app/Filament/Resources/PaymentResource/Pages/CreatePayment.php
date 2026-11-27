<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Supplier;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function afterFill(): void
    {
        $types = ['customer' => Customer::class, 'supplier' => Supplier::class, 'employee' => Employee::class];
        $prefill = array_filter([
            'direction' => request('direction'),
            'party_type' => $types[request('party_type')] ?? null,
            'party_id' => request('party_id'),
        ]);
        if (request('direction') === 'out' && ! request('party_type')) {
            $prefill['party_type'] = Supplier::class;
        }
        if ($prefill) {
            $this->form->fill(array_merge($this->data, $prefill));
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PaymentResource::normalizeData($data);
    }

    protected function getRedirectUrl(): string
    {
        return PaymentResource::getUrl('index');
    }
}
