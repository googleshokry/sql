<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AttendanceResource extends Resource
{
    use HasRoleAccess;
    use TranslatesResourceLabels;

    protected static ?string $model = Attendance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = 'الموارد البشرية';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'سجل حضور';

    protected static ?string $pluralModelLabel = 'الحضور والانصراف';

    protected static array $roles = ['hr', 'manager'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('employee_id')->label(__('الموظف'))->relationship('employee', 'name')->searchable()->preload()->required(),
            DatePicker::make('date')->label(__('التاريخ'))->default(now())->required(),
            TimePicker::make('check_in')->label(__('وقت الحضور'))->seconds(false),
            TimePicker::make('check_out')->label(__('وقت الانصراف'))->seconds(false),
            Select::make('status')->label(__('الحالة'))->options(['present' => __('حاضر'), 'late' => __('متأخر'), 'absent' => __('غائب'), 'leave' => __('إجازة')])->default('present')->required(),
            TextInput::make('notes')->label(__('ملاحظات')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.name')->label(__('الموظف'))->searchable(),
                TextColumn::make('date')->label(__('التاريخ'))->date('Y-m-d')->sortable(),
                TextColumn::make('check_in')->label(__('الحضور')),
                TextColumn::make('check_out')->label(__('الانصراف')),
                TextColumn::make('status')->label(__('الحالة'))->badge()->formatStateUsing(fn ($state) => ['present' => __('حاضر'), 'late' => __('متأخر'), 'absent' => __('غائب'), 'leave' => __('إجازة')][$state] ?? $state)->color(fn ($state) => ['present' => 'success', 'late' => 'warning', 'absent' => 'danger', 'leave' => 'info'][$state] ?? 'gray'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAttendances::route('/'),
        ];
    }
}
