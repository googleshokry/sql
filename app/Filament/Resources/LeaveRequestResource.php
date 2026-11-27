<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
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
            Select::make('employee_id')->label('الموظف')->relationship('employee', 'name')->searchable()->preload()->required(),
            Select::make('type')->label('نوع الإجازة')->options(['annual' => 'سنوية', 'sick' => 'مرضية', 'unpaid' => 'بدون راتب', 'other' => 'أخرى'])->default('annual')->required(),
            DatePicker::make('from_date')->label('من')->required(),
            DatePicker::make('to_date')->label('إلى')->required()->afterOrEqual('from_date'),
            Select::make('status')->label('الحالة')->options(['pending' => 'قيد المراجعة', 'approved' => 'موافق عليها', 'rejected' => 'مرفوضة'])->default('pending'),
            TextInput::make('reason')->label('السبب'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.name')->label('الموظف')->searchable(),
                TextColumn::make('type')->label('النوع')->formatStateUsing(fn ($state) => ['annual' => 'سنوية', 'sick' => 'مرضية', 'unpaid' => 'بدون راتب', 'other' => 'أخرى'][$state] ?? $state),
                TextColumn::make('from_date')->label('من')->date('Y-m-d'),
                TextColumn::make('to_date')->label('إلى')->date('Y-m-d'),
                TextColumn::make('days')->label('الأيام')->state(fn ($record) => $record->from_date->diffInDays($record->to_date) + 1),
                TextColumn::make('status')->label('الحالة')->badge()->formatStateUsing(fn ($state) => ['pending' => 'قيد المراجعة', 'approved' => 'موافق عليها', 'rejected' => 'مرفوضة'][$state] ?? $state)->color(fn ($state) => ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$state] ?? 'gray'),
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
