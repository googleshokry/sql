<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AdminOnly;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** إعدادات المطعم (محفوظة في السجل المركزي للمطعم) */
class RestaurantSettings extends Page
{
    use AdminOnly;

    protected string $view = 'filament.pages.restaurant-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = \App\Filament\NavGroup::Settings;

    protected static ?int $navigationSort = 0;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('إعدادات المطعم');
    }

    public function getTitle(): string
    {
        return __('إعدادات المطعم');
    }

    public function mount(): void
    {
        $tenant = tenant();
        $this->form->fill([
            'name' => $tenant->name,
            ...collect(\App\Models\Central\Tenant::DEFAULT_SETTINGS)->map(fn ($v, $k) => $tenant->setting($k))->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('بيانات المطعم'))->columns(2)->schema([
                    TextInput::make('name')->label(__('اسم المطعم'))->required()->maxLength(100),
                    TextInput::make('restaurant_phone')->label(__('التليفون'))->tel(),
                    TextInput::make('restaurant_address')->label(__('العنوان'))->columnSpanFull(),
                    TextInput::make('receipt_footer')->label(__('رسالة آخر الفاتورة'))->columnSpanFull(),
                ]),
                Section::make(__('الحسابات'))->columns(3)->schema([
                    TextInput::make('currency')->label(__('العملة'))->required()->maxLength(10),
                    TextInput::make('tax_rate')->label(__('الضريبة %'))->numeric()->minValue(0)->maxValue(100)->required(),
                    TextInput::make('service_rate')->label(__('الخدمة % (صالة فقط)'))->numeric()->minValue(0)->maxValue(100)->required(),
                ]),
                Section::make(__('الخريطة'))->description(__('مركز خريطة الطيارين (موقع المطعم)'))->columns(2)->schema([
                    TextInput::make('map_lat')->label(__('خط العرض'))->numeric()->required(),
                    TextInput::make('map_lng')->label(__('خط الطول'))->numeric()->required(),
                ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $tenant = tenant();
        $tenant->update([
            'name' => $data['name'],
            'settings' => collect($data)->except('name')->all(),
        ]);

        Notification::make()->success()->title(__('تم حفظ الإعدادات'))->send();
    }
}
