<?php

namespace App\Filament\Super\Resources;

use App\Filament\Super\Resources\SaasPlanResource\Pages;
use App\Models\SaasPlan;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class SaasPlanResource extends Resource
{
    protected static ?string $model = SaasPlan::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('باقة المنصة');
    }

    public static function getPluralModelLabel(): string
    {
        return __('باقات المنصة');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->columnSpanFull()->schema([
                TextInput::make('name')->label(__('الاسم (عربي)'))->required(),
                TextInput::make('name_en')->label(__('الاسم (إنجليزي)')),
                TextInput::make('currency')->label(__('العملة'))->default('EGP')->required(),
                TextInput::make('price_monthly')->label(__('السعر الشهري'))->numeric()->required(),
                TextInput::make('price_yearly')->label(__('السعر السنوي'))->numeric()->required(),
                TextInput::make('sort')->label(__('الترتيب'))->numeric()->default(0),
                TextInput::make('max_members')->label(__('أقصى عدد مشتركين'))->numeric()->helperText(__('اتركه فارغاً لعدد غير محدود')),
                TextInput::make('max_staff')->label(__('أقصى عدد موظفين'))->numeric()->helperText(__('اتركه فارغاً لعدد غير محدود')),
                TextInput::make('max_branches')->label(__('عدد الفروع'))->numeric()->default(1)->required(),
                Toggle::make('mobile_app')->label(__('تطبيق الموبايل'))->default(true),
                Toggle::make('store_module')->label(__('المتجر والبوفيه'))->default(true),
                Toggle::make('is_active')->label(__('مفعلة'))->default(true),
                Textarea::make('description')->label(__('الوصف'))->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label(__('الباقة'))->description(fn (SaasPlan $r) => $r->name_en),
                TextColumn::make('price_monthly')->label(__('شهري'))->numeric(2, locale: 'en')->suffix(fn (SaasPlan $r) => ' '.$r->currency),
                TextColumn::make('price_yearly')->label(__('سنوي'))->numeric(2, locale: 'en')->suffix(fn (SaasPlan $r) => ' '.$r->currency),
                TextColumn::make('max_members')->label(__('المشتركين'))->placeholder('∞'),
                TextColumn::make('max_staff')->label(__('الموظفين'))->placeholder('∞'),
                TextColumn::make('max_branches')->label(__('الفروع')),
                IconColumn::make('mobile_app')->label(__('تطبيق'))->boolean(),
                TextColumn::make('gyms_count')->label(__('الجيمات'))->counts('gyms')->badge(),
                ToggleColumn::make('is_active')->label(__('مفعلة')),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSaasPlans::route('/')];
    }
}
