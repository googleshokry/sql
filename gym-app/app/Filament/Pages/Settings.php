<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Settings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = \App\Enums\NavGroup::Admin;

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('الإعدادات');
    }

    public function getTitle(): string
    {
        return __('إعدادات الجيم');
    }

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    public function mount(): void
    {
        $values = Setting::allSettings();
        $values['allow_checkin_with_debt'] = (bool) $values['allow_checkin_with_debt'];
        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make(__('بيانات الجيم'))->columns(2)->schema([
                TextInput::make('gym_name')->label(__('اسم الجيم'))->required(),
                TextInput::make('gym_phone')->label(__('رقم التليفون')),
                TextInput::make('gym_address')->label(__('العنوان'))->columnSpanFull(),
                TextInput::make('currency')->label(__('العملة'))->required(),
                FileUpload::make('gym_logo')->label(__('الشعار'))->image()->directory('settings'),
            ]),
            Section::make(__('بيانات تظهر في تطبيق الموبايل'))->columns(2)->schema([
                Textarea::make('gym_about')->label(__('نبذة عن الجيم'))->rows(3)->columnSpanFull(),
                Textarea::make('working_hours')->label(__('مواعيد العمل'))->rows(3)->columnSpanFull()
                    ->placeholder(__('مثال: يومياً 7 صباحاً - 12 منتصف الليل، الجمعة 2 ظهراً - 12')),
                TextInput::make('gym_location_url')->label(__('رابط الموقع على الخريطة'))->url(),
                TextInput::make('gym_instagram')->label(__('إنستجرام'))->url(),
                TextInput::make('gym_facebook')->label(__('فيسبوك'))->url(),
            ]),
            Section::make(__('الحضور والتنبيهات'))->columns(2)->schema([
                TextInput::make('expiry_warning_days')->label(__('التنبيه قبل انتهاء الاشتراك بـ (يوم)'))->numeric()->minValue(1)->required(),
                TextInput::make('auto_checkout_hours')->label(__('تسجيل خروج تلقائي بعد (ساعة)'))->numeric()->minValue(1)->required(),
                Toggle::make('allow_checkin_with_debt')->label(__('السماح بالدخول مع وجود مبلغ متبقي')),
            ]),
            Section::make(__('الرسائل والإيصالات'))->schema([
                Textarea::make('whatsapp_expiry_message')->label(__('رسالة واتساب للتذكير'))
                    ->helperText(__('المتغيرات المتاحة: {name} {gym} {end_date} {plan}'))->rows(3),
                TextInput::make('receipt_footer')->label(__('تذييل الإيصال')),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([Action::make('save')->label(__('حفظ الإعدادات'))->submit('save')]),
                ]),
        ]);
    }

    public function save(): void
    {
        Setting::put($this->form->getState());
        Notification::make()->title(__('تم حفظ الإعدادات'))->success()->send();
    }
}
