<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
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
    use TranslatesPageLabels;

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
            Section::make(__('بيانات المنشأة'))->columns(3)->schema([
                TextInput::make('company_name')->label(__('اسم المنشأة (عربي)'))->required(),
                TextInput::make('company_name_en')->label(__('اسم المنشأة (إنجليزي)')),
                FileUpload::make('logo')->label(__('الشعار'))->image()->directory('settings')->disk('public'),
                TextInput::make('tax_number')->label(__('الرقم الضريبي'))->helperText(__('15 رقم - يظهر في الفاتورة ورمز QR')),
                TextInput::make('commercial_register')->label(__('السجل التجاري')),
                TextInput::make('phone')->label(__('الهاتف')),
                TextInput::make('email')->label(__('البريد الإلكتروني'))->email(),
                TextInput::make('address')->label(__('العنوان'))->columnSpan(2),
            ]),
            Section::make(__('الإعدادات المالية والضريبية'))->columns(3)->schema([
                Select::make('currency')->label(__('العملة'))->options([
                    __('ر.س') => __('ريال سعودي (ر.س)'), __('ج.م') => __('جنيه مصري (ج.م)'), __('د.إ') => __('درهم إماراتي (د.إ)'),
                    __('د.ك') => __('دينار كويتي (د.ك)'), __('ر.ق') => __('ريال قطري (ر.ق)'), __('ر.ع') => __('ريال عماني (ر.ع)'), __('د.أ') => __('دينار أردني (د.أ)'), '$' => __('دولار ($)'),
                ])->required(),
                TextInput::make('default_tax_rate')->label(__('نسبة الضريبة الافتراضية %'))->numeric()->required(),
                Toggle::make('zatca_qr')->label(__('إظهار رمز QR (هيئة الزكاة والضريبة) على الفاتورة'))->inline(false),
                Toggle::make('allow_negative_stock')->label(__('السماح بالبيع بالسالب (بدون رصيد)'))->inline(false),
            ]),
            Section::make(__('الفواتير'))->columns(2)->schema([
                Textarea::make('invoice_terms')->label(__('الشروط والأحكام الافتراضية'))->rows(3),
                Textarea::make('invoice_footer')->label(__('تذييل الفاتورة'))->rows(3),
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
        Notification::make()->title(__('تم حفظ الإعدادات'))->success()->send();
    }
}
