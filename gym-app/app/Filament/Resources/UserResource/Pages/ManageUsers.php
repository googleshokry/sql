<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('attach')->label(__('إضافة موظف موجود'))->icon(Heroicon::OutlinedUserPlus)->color('gray')
                ->visible(fn () => UserResource::canCreate())
                ->schema([
                    TextInput::make('email')->label(__('البريد'))->email()->required()->exists('users', 'email'),
                    Select::make('role')->label(__('الصلاحية'))->options(User::roles())->default(User::ROLE_RECEPTIONIST)->required(),
                ])
                ->action(function (array $data) {
                    $user = User::where('email', $data['email'])->firstOrFail();
                    $user->gyms()->syncWithoutDetaching([Tenancy::id() => ['role' => $data['role']]]);
                    Notification::make()->title(__('تمت إضافة الموظف'))->success()->send();
                }),
            CreateAction::make()
                ->using(function (array $data) {
                    $role = $data['role'];
                    unset($data['role']);
                    $user = User::create($data + ['locale' => app()->getLocale()]);
                    $user->gyms()->attach(Tenancy::id(), ['role' => $role]);

                    return $user;
                }),
        ];
    }
}
