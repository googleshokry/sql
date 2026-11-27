<?php

namespace App\Filament\Auth;

use App\Services\CompanyProvisioner;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * Self-service sign-up: creates a new company on a free trial plus its owner account.
 */
class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('company_name')->label(__('اسم الشركة / المنشأة'))->required()->maxLength(255)->autofocus(),
            $this->getNameFormComponent()->autofocus(false),
            TextInput::make('phone')->label(__('الجوال / واتساب'))->tel()->maxLength(30),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        return CompanyProvisioner::register([
            'company_name' => $data['company_name'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'locale' => app()->getLocale(),
        ]);
    }
}
