<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ManagerOnly;
use App\Filament\Resources\TrainerResource\Pages;
use App\Models\Setting;
use App\Models\Trainer;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TrainerResource extends Resource
{
    use ManagerOnly;

    protected static ?string $model = Trainer::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'الإدارة';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'مدرب';

    protected static ?string $pluralModelLabel = 'المدربين';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->columnSpanFull()->schema([
                TextInput::make('name')->label('الاسم')->required(),
                TextInput::make('phone')->label('الموبايل')->tel(),
                TextInput::make('email')->label('البريد')->email(),
                TextInput::make('specialty')->label('التخصص')->placeholder('كمال أجسام، فيتنس، كارديو ...'),
                Select::make('gender')->label('النوع')->options(['male' => 'ذكر', 'female' => 'أنثى'])->default('male')->required(),
                DatePicker::make('hired_at')->label('تاريخ التعيين'),
                TextInput::make('salary')->label('المرتب الشهري')->numeric()->default(0)->suffix(Setting::currency()),
                TextInput::make('commission_percent')->label('نسبة العمولة من الاشتراكات')->numeric()->default(0)->suffix('%'),
                Toggle::make('is_active')->label('نشط')->default(true)->inline(false),
                FileUpload::make('photo')->label('الصورة')->image()->avatar()->directory('trainers'),
                Textarea::make('notes')->label('ملاحظات')->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')->label('')->circular()
                    ->defaultImageUrl(fn (Trainer $r) => \App\Support\Avatar::for($r->name, '#374151')),
                TextColumn::make('name')->label('الاسم')->searchable()->sortable(),
                TextColumn::make('phone')->label('الموبايل')->searchable(),
                TextColumn::make('specialty')->label('التخصص'),
                TextColumn::make('members_count')->label('المشتركين')->counts('members')->badge(),
                TextColumn::make('salary')->label('المرتب')->numeric(2, locale: 'en'),
                TextColumn::make('commission_percent')->label('العمولة')->suffix('%'),
                ToggleColumn::make('is_active')->label('نشط'),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make(), DeleteAction::make(), RestoreAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageTrainers::route('/')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
