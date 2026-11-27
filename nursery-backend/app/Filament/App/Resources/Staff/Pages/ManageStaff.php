<?php

namespace App\Filament\App\Resources\Staff\Pages;

use App\Actions\OnboardNursery;
use App\Filament\App\Resources\Staff\StaffResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Validation\ValidationException;

class ManageStaff extends ManageRecords
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(function (array $data): User {
                $nursery = Filament::getTenant();

                // Reuse the account if this person already exists (e.g. works at another nursery).
                $user = User::where('email', $data['email'])->first();

                if (! $user) {
                    if (blank($data['password'] ?? null)) {
                        throw ValidationException::withMessages(['password' => __('validation.required', ['attribute' => __('app.password')])]);
                    }

                    $user = User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'phone' => $data['phone'] ?? null,
                        'password' => $data['password'],
                        'locale' => $nursery->default_locale,
                    ]);
                }

                $nursery->members()->syncWithoutDetaching([$user->id => ['branch_id' => $data['branch_id'] ?? null]]);
                app(OnboardNursery::class)->assignRole($nursery, $user, $data['role']);

                return $user;
            }),
        ];
    }
}
