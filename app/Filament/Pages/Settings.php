<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** إعدادات الشركة والنظام */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'إعدادات الشركة';

    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $values = Setting::values();
        foreach (['allow_negative_stock', 'zatca_qr'] as $bool) {
            $values[$bool] = (bool) $values[$bool];
        }
        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('بيانات المنشأة')->columns(3)->schema([
                TextInput::make('company_name')->label('اسم المنشأة (عربي)')->required(),
                TextInput::make('company_name_en')->label('اسم المنشأة (إنجليزي)'),
                FileUpload::make('logo')->label('الشعار')->image()->directory('settings')->disk('public'),
                TextInput::make('tax_number')->label('الرقم الضريبي')->helperText('15 رقم - يظهر في الفاتورة ورمز QR'),
                TextInput::make('commercial_register')->label('السجل التجاري'),
                TextInput::make('phone')->label('الهاتف'),
                TextInput::make('email')->label('البريد الإلكتروني')->email(),
                TextInput::make('address')->label('العنوان')->columnSpan(2),
            ]),
            Section::make('الإعدادات المالية والضريبية')->columns(3)->schema([
                Select::make('currency')->label('العملة')->options([
                    'ر.س' => 'ريال سعودي (ر.س)', 'ج.م' => 'جنيه مصري (ج.م)', 'د.إ' => 'درهم إماراتي (د.إ)',
                    'د.ك' => 'دينار كويتي (د.ك)', 'ر.ق' => 'ريال قطري (ر.ق)', 'ر.ع' => 'ريال عماني (ر.ع)', 'د.أ' => 'دينار أردني (د.أ)', '$' => 'دولار ($)',
                ])->required(),
                TextInput::make('default_tax_rate')->label('نسبة الضريبة الافتراضية %')->numeric()->required(),
                Toggle::make('zatca_qr')->label('إظهار رمز QR (هيئة الزكاة والضريبة) على الفاتورة')->inline(false),
                Toggle::make('allow_negative_stock')->label('السماح بالبيع بالسالب (بدون رصيد)')->inline(false),
            ]),
            Section::make('الفواتير')->columns(2)->schema([
                Textarea::make('invoice_terms')->label('الشروط والأحكام الافتراضية')->rows(3),
                Textarea::make('invoice_footer')->label('تذييل الفاتورة')->rows(3),
            ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        foreach (['allow_negative_stock', 'zatca_qr'] as $bool) {
            $data[$bool] = ! empty($data[$bool]) ? '1' : '0';
        }
        Setting::setMany($data);
        Notification::make()->title('تم حفظ الإعدادات')->success()->send();
    }
}
