<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Platform\Resources\PlanResource\Pages;
use App\Models\Plan;
use App\Support\Translate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Subscription plans: prices, limits and modules. */
class PlanResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'باقة';

    protected static ?string $pluralModelLabel = 'الباقات';

    public static function canDelete(Model $record): bool
    {
        return ! $record->companies()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextInput::make('name')->label(__('الاسم (عربي)'))->required(),
                TextInput::make('name_en')->label(__('الاسم (إنجليزي)')),
                TextInput::make('code')->label(__('الكود'))->required()->unique(ignoreRecord: true)->alphaDash(),
                TextInput::make('monthly_price')->label(__('السعر الشهري'))->numeric()->required()->default(0),
                TextInput::make('yearly_price')->label(__('السعر السنوي'))->numeric()->required()->default(0),
                TextInput::make('trial_days')->label(__('أيام التجربة'))->numeric()->integer()->default(14),
            ]),
            Section::make(__('الحدود (0 = غير محدود)'))->columns(3)->columnSpanFull()->schema([
                TextInput::make('max_users')->label(__('المستخدمين'))->numeric()->integer()->default(1),
                TextInput::make('max_branches')->label(__('الفروع'))->numeric()->integer()->default(1),
                TextInput::make('max_warehouses')->label(__('المخازن'))->numeric()->integer()->default(1),
            ]),
            Section::make(__('الوحدات المتاحة'))->columnSpanFull()->schema([
                CheckboxList::make('features')->hiddenLabel()->options(Translate::options(Plan::FEATURES))->columns(3),
                Toggle::make('is_active')->label(__('متاحة للاشتراك'))->default(true),
                TextInput::make('sort')->label(__('الترتيب'))->numeric()->integer()->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('companies'))
            ->columns([
                TextColumn::make('name')->label(__('الاسم'))->formatStateUsing(fn ($state, Plan $record) => $record->displayName())->weight('bold'),
                TextColumn::make('monthly_price')->label(__('السعر الشهري'))->numeric(2),
                TextColumn::make('yearly_price')->label(__('السعر السنوي'))->numeric(2),
                TextColumn::make('max_users')->label(__('المستخدمين'))->formatStateUsing(fn ($state) => $state ?: '∞'),
                TextColumn::make('max_branches')->label(__('الفروع'))->formatStateUsing(fn ($state) => $state ?: '∞'),
                TextColumn::make('features')->label(__('الوحدات المتاحة'))->badge()
                    ->formatStateUsing(fn ($state) => __(Plan::FEATURES[$state] ?? $state)),
                TextColumn::make('companies_count')->label(__('الشركات')),
                IconColumn::make('is_active')->label(__('نشط'))->boolean(),
            ])
            ->defaultSort('sort')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePlans::route('/')];
    }
}
