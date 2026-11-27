<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\CrmActivityResource\Pages;
use App\Models\CrmActivity;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CrmActivityResource extends Resource
{
    use HasRoleAccess;

    protected static ?string $model = CrmActivity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'المبيعات';

    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'متابعة';

    protected static ?string $pluralModelLabel = 'متابعة العملاء (CRM)';

    protected static array $roles = ['sales', 'manager'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('customer_id')->label('العميل')->relationship('customer', 'name')->searchable()->preload()->required(),
            Select::make('type')->label('النوع')->options(CrmActivity::TYPES)->default('call')->required(),
            TextInput::make('subject')->label('الموضوع')->required()->columnSpanFull(),
            DateTimePicker::make('scheduled_at')->label('الموعد')->seconds(false),
            Select::make('user_id')->label('المسؤول')->relationship('user', 'name')->default(fn () => auth()->id()),
            Select::make('status')->label('الحالة')->options(CrmActivity::STATUSES)->default('pending'),
            Textarea::make('notes')->label('ملاحظات / نتيجة المتابعة')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('scheduled_at')->label('الموعد')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('customer.name')->label('العميل')->searchable(),
                TextColumn::make('type')->label('النوع')->formatStateUsing(fn ($state) => CrmActivity::TYPES[$state] ?? $state),
                TextColumn::make('subject')->label('الموضوع')->searchable()->limit(40),
                TextColumn::make('user.name')->label('المسؤول'),
                TextColumn::make('status')->label('الحالة')->badge()->formatStateUsing(fn ($state) => CrmActivity::STATUSES[$state] ?? $state)->color(fn ($state) => ['pending' => 'warning', 'done' => 'success', 'cancelled' => 'danger'][$state] ?? 'gray'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCrmActivities::route('/'),
        ];
    }
}
