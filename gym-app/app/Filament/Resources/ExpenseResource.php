<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ManagerOnly;
use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Setting;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseResource extends Resource
{
    use ManagerOnly;

    protected static ?string $model = Expense::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingDown;

    protected static string|\UnitEnum|null $navigationGroup = 'المالية';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'مصروف';

    protected static ?string $pluralModelLabel = 'المصروفات';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->columnSpanFull()->schema([
                TextInput::make('title')->label('البيان')->required(),
                Select::make('expense_category_id')->label('البند')->relationship('category', 'name')
                    ->searchable()->preload()
                    ->createOptionForm([TextInput::make('name')->label('اسم البند')->required()]),
                TextInput::make('amount')->label('المبلغ')->numeric()->minValue(0)->required()->suffix(Setting::currency()),
                DatePicker::make('spent_at')->label('التاريخ')->default(now())->required(),
                Select::make('method')->label('طريقة الدفع')->options(Payment::METHODS)->default('cash')->required(),
                FileUpload::make('attachment')->label('مرفق (فاتورة)')->directory('expenses'),
                Textarea::make('notes')->label('ملاحظات')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('spent_at', 'desc')
            ->columns([
                TextColumn::make('spent_at')->label('التاريخ')->date('Y-m-d')->sortable(),
                TextColumn::make('title')->label('البيان')->searchable(),
                TextColumn::make('category.name')->label('البند')->badge(),
                TextColumn::make('amount')->label('المبلغ')->numeric(2, locale: 'en')->sortable()
                    ->summarize(Sum::make()->label('الإجمالي')->numeric(2, locale: 'en')),
                TextColumn::make('method')->label('الطريقة')->formatStateUsing(fn ($state) => Payment::METHODS[$state] ?? $state),
                TextColumn::make('user.name')->label('بواسطة')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('expense_category_id')->label('البند')->relationship('category', 'name'),
                Filter::make('date')->schema([
                    DatePicker::make('from')->label('من'),
                    DatePicker::make('until')->label('إلى'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('spent_at', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('spent_at', '<=', $d))),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageExpenses::route('/')];
    }
}
