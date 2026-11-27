<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\LeaveRequestResource\Pages;
use App\Models\LeaveRequest;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LeaveRequestResource extends Resource
{
    use HasRoleAccess;
    use TranslatesResourceLabels;

    protected static ?string $model = LeaveRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static string|UnitEnum|null $navigationGroup = 'الموارد البشرية';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'طلب إجازة';

    protected static ?string $pluralModelLabel = 'الإجازات';

    protected static array $roles = ['hr', 'manager'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('employee_id')->label(__('الموظف'))->relationship('employee', 'name')->searchable()->preload()->required(),
            Select::make('type')->label(__('نوع الإجازة'))->options(['annual' => __('سنوية'), 'sick' => __('مرضية'), 'unpaid' => __('بدون راتب'), 'other' => __('أخرى')])->default('annual')->required(),
            DatePicker::make('from_date')->label(__('من'))->required(),
            DatePicker::make('to_date')->label(__('إلى'))->required()->afterOrEqual('from_date'),
            Select::make('status')->label(__('الحالة'))->options(['pending' => __('قيد المراجعة'), 'approved' => __('موافق عليها'), 'rejected' => __('مرفوضة')])->default('pending'),
            TextInput::make('reason')->label(__('السبب')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.name')->label(__('الموظف'))->searchable(),
                TextColumn::make('type')->label(__('النوع'))->formatStateUsing(fn ($state) => ['annual' => __('سنوية'), 'sick' => __('مرضية'), 'unpaid' => __('بدون راتب'), 'other' => __('أخرى')][$state] ?? $state),
                TextColumn::make('from_date')->label(__('من'))->date('Y-m-d'),
                TextColumn::make('to_date')->label(__('إلى'))->date('Y-m-d'),
                TextColumn::make('days')->label(__('الأيام'))->state(fn ($record) => $record->from_date->diffInDays($record->to_date) + 1),
                TextColumn::make('status')->label(__('الحالة'))->badge()->formatStateUsing(fn ($state) => ['pending' => __('قيد المراجعة'), 'approved' => __('موافق عليها'), 'rejected' => __('مرفوضة')][$state] ?? $state)->color(fn ($state) => ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$state] ?? 'gray'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageLeaveRequests::route('/'),
        ];
    }
}
