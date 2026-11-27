<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Support\Tenancy;
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
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class EmployeeResource extends Resource
{
    use HasRoleAccess;
    use TranslatesResourceLabels;

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
            Section::make(__('البيانات الشخصية'))->columns(3)->columnSpanFull()->schema([
                TextInput::make('code')->label(__('الرقم الوظيفي'))->placeholder(__('تلقائي'))->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('company_id', Tenancy::id())),
                TextInput::make('name')->label(__('الاسم'))->required(),
                TextInput::make('national_id')->label(__('رقم الهوية / الإقامة')),
                TextInput::make('nationality')->label(__('الجنسية')),
                TextInput::make('phone')->label(__('الجوال')),
                TextInput::make('email')->label(__('البريد'))->email(),
            ]),
            Section::make(__('بيانات الوظيفة'))->columns(3)->columnSpanFull()->schema([
                TextInput::make('job_title')->label(__('المسمى الوظيفي')),
                Select::make('department_id')->label(__('القسم'))->relationship('department', 'name'),
                Select::make('branch_id')->label(__('الفرع'))->relationship('branch', 'name'),
                DatePicker::make('hire_date')->label(__('تاريخ التعيين')),
                Select::make('user_id')->label(__('حساب المستخدم (لحساب العمولة)'))->relationship('user', 'name', fn ($query) => $query->where('company_id', Tenancy::id())),
                Toggle::make('is_active')->label(__('على رأس العمل'))->default(true)->inline(false),
            ]),
            Section::make(__('الراتب والبدلات'))->columns(3)->columnSpanFull()->schema([
                TextInput::make('basic_salary')->label(__('الراتب الأساسي'))->numeric()->default(0),
                TextInput::make('housing_allowance')->label(__('بدل السكن'))->numeric()->default(0),
                TextInput::make('transport_allowance')->label(__('بدل المواصلات'))->numeric()->default(0),
                TextInput::make('other_allowances')->label(__('بدلات أخرى'))->numeric()->default(0),
                TextInput::make('commission_percent')->label(__('نسبة العمولة من المبيعات %'))->numeric()->default(0),
                TextInput::make('iban')->label('IBAN'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('الرقم'))->searchable(),
                TextColumn::make('name')->label(__('الاسم'))->searchable()->weight('bold'),
                TextColumn::make('job_title')->label(__('الوظيفة')),
                TextColumn::make('department.name')->label(__('القسم')),
                TextColumn::make('phone')->label(__('الجوال')),
                TextColumn::make('gross')->label(__('إجمالي الراتب'))->state(fn (Employee $record) => $record->grossSalary())->numeric(2),
                IconColumn::make('is_active')->label(__('نشط'))->boolean(),
            ])
            ->filters([SelectFilter::make('department_id')->label(__('القسم'))->relationship('department', 'name')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageEmployees::route('/')];
    }
}
