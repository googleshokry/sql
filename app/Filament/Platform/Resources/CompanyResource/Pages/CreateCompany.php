<?php

namespace App\Filament\Platform\Resources\CompanyResource\Pages;

use App\Filament\Platform\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CompanyProvisioner;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    /** Creates the company with its default data and owner account. */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $owner = [
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => $data['owner_password'],
            ];
            unset($data['owner_name'], $data['owner_email'], $data['owner_password']);

            $company = Company::create($data);
            CompanyProvisioner::provision($company, $owner);

            return $company;
        });
    }
}
