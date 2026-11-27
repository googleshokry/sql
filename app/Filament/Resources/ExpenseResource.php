<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Models\Treasury;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ExpenseResource extends Resource
{
    use HasRoleAccess;

    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'المالية';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'مصروف';

    protected static ?string $pluralModelLabel = 'المصروفات';

    protected static array $roles = ['accountant', 'manager'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            DatePicker::make('date')->label('التاريخ')->default(now())->required(),
            Select::make('expense_category_id')->label('بند المصروف')->relationship('category', 'name')->required()->preload()
                ->createOptionForm([TextInput::make('name')->label('الاسم')->required()]),
            Select::make('treasury_id')->label('صرف من')->required()
                ->options(fn () => Treasury::query()->where('is_active', true)->pluck('name', 'id'))
                ->default(fn () => Treasury::query()->where('is_default', true)->value('id')),
            TextInput::make('amount')->label('المبلغ قبل الضريبة')->numeric()->required()->minValue(0.01),
            TextInput::make('tax_rate')->label('نسبة الضريبة %')->numeric()->default(0),
            Select::make('supplier_id')->label('المورد (اختياري)')->relationship('supplier', 'name')->searchable()->preload(),
            TextInput::make('description')->label('البيان')->columnSpan(2),
            FileUpload::make('attachment')->label('مرفق (فاتورة)')->directory('expenses'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('الرقم')->searchable(),
                TextColumn::make('date')->label('التاريخ')->date('Y-m-d')->sortable(),
                TextColumn::make('category.name')->label('البند'),
                TextColumn::make('description')->label('البيان')->limit(30)->searchable(),
                TextColumn::make('treasury.name')->label('الخزينة'),
                TextColumn::make('amount')->label('المبلغ')->numeric(2),
                TextColumn::make('tax_amount')->label('الضريبة')->numeric(2)->summarize(Sum::make()->label('')),
                TextColumn::make('total')->label('الإجمالي')->numeric(2)->weight('bold')->summarize(Sum::make()->label('')),
            ])
            ->filters([
                SelectFilter::make('expense_category_id')->label('البند')->relationship('category', 'name'),
                SelectFilter::make('treasury_id')->label('الخزينة')->relationship('treasury', 'name'),
                Filter::make('date')
                    ->schema([DatePicker::make('from')->label('من'), DatePicker::make('to')->label('إلى')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
                        ->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageExpenses::route('/')];
    }
}
