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

    protected static string|\UnitEnum|null $navigationGroup = 'الإدارة';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'الإعدادات';

    protected static ?string $title = 'إعدادات الجيم';

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
            Section::make('بيانات الجيم')->columns(2)->schema([
                TextInput::make('gym_name')->label('اسم الجيم')->required(),
                TextInput::make('gym_phone')->label('رقم التليفون'),
                TextInput::make('gym_address')->label('العنوان')->columnSpanFull(),
                TextInput::make('currency')->label('العملة')->required(),
                FileUpload::make('gym_logo')->label('الشعار')->image()->directory('settings'),
            ]),
            Section::make('الحضور والتنبيهات')->columns(2)->schema([
                TextInput::make('expiry_warning_days')->label('التنبيه قبل انتهاء الاشتراك بـ (يوم)')->numeric()->minValue(1)->required(),
                TextInput::make('auto_checkout_hours')->label('تسجيل خروج تلقائي بعد (ساعة)')->numeric()->minValue(1)->required(),
                Toggle::make('allow_checkin_with_debt')->label('السماح بالدخول مع وجود مبلغ متبقي'),
            ]),
            Section::make('الرسائل والإيصالات')->schema([
                Textarea::make('whatsapp_expiry_message')->label('رسالة واتساب للتذكير')
                    ->helperText('المتغيرات المتاحة: {name} {gym} {end_date} {plan}')->rows(3),
                TextInput::make('receipt_footer')->label('تذييل الإيصال'),
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
                    Actions::make([Action::make('save')->label('حفظ الإعدادات')->submit('save')]),
                ]),
        ]);
    }

    public function save(): void
    {
        Setting::put($this->form->getState());
        Notification::make()->title('تم حفظ الإعدادات')->success()->send();
    }
}
