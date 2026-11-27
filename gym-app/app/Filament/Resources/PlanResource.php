<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ManagerOnly;
use App\Filament\Resources\PlanResource\Pages;
use App\Models\Plan;
use App\Models\Setting;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlanResource extends Resource
{
    use ManagerOnly;

    protected static ?string $model = Plan::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'العضويات';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'باقة';

    protected static ?string $pluralModelLabel = 'الباقات';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->columnSpanFull()->schema([
                TextInput::make('name')->label('اسم الباقة')->required()->maxLength(255)->columnSpan(2),
                ColorPicker::make('color')->label('اللون'),
                TextInput::make('duration_days')->label('المدة (بالأيام)')->numeric()->minValue(1)->required()->default(30),
                TextInput::make('price')->label('السعر')->numeric()->minValue(0)->required()->suffix(Setting::currency()),
                TextInput::make('sessions_count')->label('عدد الحصص')->numeric()->minValue(1)
                    ->helperText('اتركه فارغاً لعدد غير محدود'),
                TextInput::make('freeze_days')->label('أيام التجميد المسموحة')->numeric()->minValue(0)->default(0),
                TextInput::make('guest_invitations')->label('عدد الدعوات (ضيوف)')->numeric()->minValue(0)->default(0),
                Toggle::make('includes_trainer')->label('تشمل مدرب خاص')->inline(false),
                Textarea::make('description')->label('الوصف')->columnSpanFull(),
                Toggle::make('is_active')->label('مفعلة')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color')->label(''),
                TextColumn::make('name')->label('الباقة')->searchable()->sortable()->weight('bold'),
                TextColumn::make('duration_days')->label('المدة')->suffix(' يوم')->sortable(),
                TextColumn::make('price')->label('السعر')->numeric(2, locale: 'en')->suffix(' '.Setting::currency())->sortable(),
                TextColumn::make('sessions_count')->label('الحصص')->placeholder('غير محدود'),
                TextColumn::make('freeze_days')->label('أيام التجميد'),
                TextColumn::make('guest_invitations')->label('دعوات'),
                IconColumn::make('includes_trainer')->label('مدرب')->boolean(),
                TextColumn::make('subscriptions_count')->label('عدد الاشتراكات')->counts('subscriptions')->badge(),
                ToggleColumn::make('is_active')->label('مفعلة'),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make(), DeleteAction::make(), RestoreAction::make(), ForceDeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePlans::route('/')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
