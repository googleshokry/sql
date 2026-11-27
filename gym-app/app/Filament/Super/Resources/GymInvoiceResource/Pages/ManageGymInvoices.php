<?php

namespace App\Filament\Super\Resources\GymInvoiceResource\Pages;

use App\Filament\Super\Resources\GymInvoiceResource;
use App\Models\Gym;
use App\Models\GymInvoice;
use App\Models\SaasPlan;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGymInvoices extends ManageRecords
{
    protected static string $resource = GymInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data) => GymInvoice::issueFor(
                Gym::findOrFail($data['gym_id']),
                SaasPlan::find($data['saas_plan_id']),
                $data['billing_cycle'],
            )),
        ];
    }
}
