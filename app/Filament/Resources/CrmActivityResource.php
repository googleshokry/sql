<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\CrmActivityResource\Pages;
use App\Models\CrmActivity;
use App\Support\Tenancy;
use App\Support\Translate;
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
    use TranslatesResourceLabels;

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
            Select::make('customer_id')->label(__('العميل'))->relationship('customer', 'name')->searchable()->preload()->required(),
            Select::make('type')->label(__('النوع'))->options(Translate::options(CrmActivity::TYPES))->default('call')->required(),
            TextInput::make('subject')->label(__('الموضوع'))->required()->columnSpanFull(),
            DateTimePicker::make('scheduled_at')->label(__('الموعد'))->seconds(false),
            Select::make('user_id')->label(__('المسؤول'))->relationship('user', 'name', fn ($query) => $query->where('company_id', Tenancy::id()))->default(fn () => auth()->id()),
            Select::make('status')->label(__('الحالة'))->options(Translate::options(CrmActivity::STATUSES))->default('pending'),
            Textarea::make('notes')->label(__('ملاحظات / نتيجة المتابعة'))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('scheduled_at')->label(__('الموعد'))->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('customer.name')->label(__('العميل'))->searchable(),
                TextColumn::make('type')->label(__('النوع'))->formatStateUsing(fn ($state) => __(CrmActivity::TYPES[$state] ?? $state)),
                TextColumn::make('subject')->label(__('الموضوع'))->searchable()->limit(40),
                TextColumn::make('user.name')->label(__('المسؤول')),
                TextColumn::make('status')->label(__('الحالة'))->badge()->formatStateUsing(fn ($state) => __(CrmActivity::STATUSES[$state] ?? $state))->color(fn ($state) => ['pending' => 'warning', 'done' => 'success', 'cancelled' => 'danger'][$state] ?? 'gray'),
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
