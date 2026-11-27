<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class EmployeeResource extends Resource
{
    use HasRoleAccess;

    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'الموارد البشرية';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'موظف';

    protected static ?string $pluralModelLabel = 'الموظفين';

    protected static ?string $recordTitleAttribute = 'name';

    protected static array $roles = ['hr', 'manager'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('البيانات الشخصية')->columns(3)->columnSpanFull()->schema([
                TextInput::make('code')->label('الرقم الوظيفي')->placeholder('تلقائي')->unique(ignoreRecord: true),
                TextInput::make('name')->label('الاسم')->required(),
                TextInput::make('national_id')->label('رقم الهوية / الإقامة'),
                TextInput::make('nationality')->label('الجنسية'),
                TextInput::make('phone')->label('الجوال'),
                TextInput::make('email')->label('البريد')->email(),
            ]),
            Section::make('بيانات الوظيفة')->columns(3)->columnSpanFull()->schema([
                TextInput::make('job_title')->label('المسمى الوظيفي'),
                Select::make('department_id')->label('القسم')->relationship('department', 'name'),
                Select::make('branch_id')->label('الفرع')->relationship('branch', 'name'),
                DatePicker::make('hire_date')->label('تاريخ التعيين'),
                Select::make('user_id')->label('حساب المستخدم (لحساب العمولة)')->relationship('user', 'name'),
                Toggle::make('is_active')->label('على رأس العمل')->default(true)->inline(false),
            ]),
            Section::make('الراتب والبدلات')->columns(3)->columnSpanFull()->schema([
                TextInput::make('basic_salary')->label('الراتب الأساسي')->numeric()->default(0),
                TextInput::make('housing_allowance')->label('بدل السكن')->numeric()->default(0),
                TextInput::make('transport_allowance')->label('بدل المواصلات')->numeric()->default(0),
                TextInput::make('other_allowances')->label('بدلات أخرى')->numeric()->default(0),
                TextInput::make('commission_percent')->label('نسبة العمولة من المبيعات %')->numeric()->default(0),
                TextInput::make('iban')->label('IBAN'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('الرقم')->searchable(),
                TextColumn::make('name')->label('الاسم')->searchable()->weight('bold'),
                TextColumn::make('job_title')->label('الوظيفة'),
                TextColumn::make('department.name')->label('القسم'),
                TextColumn::make('phone')->label('الجوال'),
                TextColumn::make('gross')->label('إجمالي الراتب')->state(fn (Employee $record) => $record->grossSalary())->numeric(2),
                IconColumn::make('is_active')->label('نشط')->boolean(),
            ])
            ->filters([SelectFilter::make('department_id')->label('القسم')->relationship('department', 'name')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageEmployees::route('/')];
    }
}
