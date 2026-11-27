<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Gym;
use App\Models\SaasPlan;
use App\Services\GymOnboarding;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Self-service signup: creates a new gym (or branch) with a free trial. */
class RegisterGym extends RegisterTenant
{
    public static function getLabel(): string
    {
        return __('إنشاء جيم جديد');
    }

    public static function canView(): bool
    {
        return auth()->check() && GymOnboarding::canAddBranch(auth()->user());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('اسم الجيم'))->required()->maxLength(100)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug((string) $state))),
            TextInput::make('slug')->label(__('رابط الجيم / كود الجيم'))
                ->helperText(__('حروف إنجليزية وأرقام فقط. يستخدمه المشتركون للدخول على التطبيق.'))
                ->required()->minLength(3)->maxLength(40)->alphaDash()
                ->unique(Gym::class, 'slug')
                ->prefix(url('/admin').'/'),
            TextInput::make('phone')->label(__('رقم التليفون'))->tel(),
            TextInput::make('address')->label(__('العنوان')),
            Radio::make('saas_plan_id')->label(__('الباقة (بعد الفترة التجريبية)'))
                ->options(fn () => SaasPlan::where('is_active', true)->orderBy('sort')->get()->mapWithKeys(fn (SaasPlan $p) => [
                    $p->id => $p->localizedName().' — '.number_format((float) $p->price_monthly).' '.$p->currency.' / '.__('شهرياً'),
                ]))
                ->default(fn () => SaasPlan::where('is_active', true)->orderBy('sort')->value('id')),
        ]);
    }

    protected function handleRegistration(array $data): Model
    {
        return GymOnboarding::create(auth()->user(), $data);
    }
}
