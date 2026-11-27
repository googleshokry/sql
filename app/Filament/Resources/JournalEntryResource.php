<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\JournalEntryResource\Pages;
use App\Models\Account;
use App\Models\JournalEntry;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn as EntryColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

/** القيود اليومية */
class JournalEntryResource extends Resource
{
    use HasRoleAccess;

    protected static ?string $model = JournalEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'المحاسبة';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'قيد يومية';

    protected static ?string $pluralModelLabel = 'القيود اليومية';

    protected static ?string $recordTitleAttribute = 'number';

    protected static array $roles = ['accountant'];

    public static function canEdit(Model $record): bool
    {
        return ! $record->is_auto;
    }

    public static function canDelete(Model $record): bool
    {
        return ! $record->is_auto;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                DatePicker::make('date')->label('التاريخ')->default(now())->required(),
                TextInput::make('description')->label('البيان')->required()->columnSpan(2),
            ]),
            Section::make('أطراف القيد')->columnSpanFull()->schema([
                Repeater::make('lines')->hiddenLabel()->relationship()
                    ->table([
                        TableColumn::make('الحساب')->markAsRequired()->width('40%'),
                        TableColumn::make('مدين'),
                        TableColumn::make('دائن'),
                        TableColumn::make('البيان'),
                    ])
                    ->schema([
                        Select::make('account_id')->hiddenLabel()->required()->searchable()
                            ->options(fn () => Account::query()->where('is_group', false)->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => "{$a->code} - {$a->name}"])),
                        TextInput::make('debit')->hiddenLabel()->numeric()->default(0)->live(onBlur: true),
                        TextInput::make('credit')->hiddenLabel()->numeric()->default(0)->live(onBlur: true),
                        TextInput::make('memo')->hiddenLabel(),
                    ])
                    ->minItems(2)->defaultItems(2)->addActionLabel('إضافة سطر')->live(),
                TextEntry::make('balance_check')->hiddenLabel()->state(function (Get $get) {
                    $lines = collect($get('lines') ?? []);
                    $d = $lines->sum(fn ($l) => (float) ($l['debit'] ?? 0));
                    $c = $lines->sum(fn ($l) => (float) ($l['credit'] ?? 0));
                    $ok = abs($d - $c) < 0.01 && $d > 0;

                    return new HtmlString('<b>إجمالي المدين: '.number_format($d, 2).' — إجمالي الدائن: '.number_format($c, 2).'</b> '
                        .($ok ? '<span style="color:#16a34a">✔ القيد متوازن</span>' : '<span style="color:#dc2626">✘ القيد غير متوازن (الفرق '.number_format($d - $c, 2).')</span>'));
                }),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('number')->label('رقم القيد')->weight('bold'),
                TextEntry::make('date')->label('التاريخ')->date('Y-m-d'),
                TextEntry::make('total')->label('الإجمالي')->numeric(2),
                TextEntry::make('is_auto')->label('النوع')->formatStateUsing(fn ($state) => $state ? 'قيد آلي' : 'قيد يدوي')->badge(),
                TextEntry::make('description')->label('البيان')->columnSpanFull(),
            ]),
            Section::make('أطراف القيد')->columnSpanFull()->schema([
                RepeatableEntry::make('lines')->hiddenLabel()
                    ->table([EntryColumn::make('رقم الحساب'), EntryColumn::make('الحساب'), EntryColumn::make('مدين'), EntryColumn::make('دائن'), EntryColumn::make('الطرف'), EntryColumn::make('البيان')])
                    ->schema([
                        TextEntry::make('account.code'),
                        TextEntry::make('account.name'),
                        TextEntry::make('debit')->numeric(2),
                        TextEntry::make('credit')->numeric(2),
                        TextEntry::make('party.name')->placeholder('-'),
                        TextEntry::make('memo')->placeholder('-'),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('رقم القيد')->searchable()->weight('bold'),
                TextColumn::make('date')->label('التاريخ')->date('Y-m-d')->sortable(),
                TextColumn::make('description')->label('البيان')->searchable()->limit(60)->wrap(),
                TextColumn::make('total')->label('المبلغ')->numeric(2),
                IconColumn::make('is_auto')->label('آلي')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_auto')->label('نوع القيد')->trueLabel('آلية فقط')->falseLabel('يدوية فقط'),
                Filter::make('date')
                    ->schema([DatePicker::make('from')->label('من'), DatePicker::make('to')->label('إلى')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
                        ->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([ViewAction::make(), EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournalEntries::route('/'),
            'create' => Pages\CreateJournalEntry::route('/create'),
            'view' => Pages\ViewJournalEntry::route('/{record}'),
            'edit' => Pages\EditJournalEntry::route('/{record}/edit'),
        ];
    }

    /** التحقق من توازن القيد قبل الحفظ */
    public static function assertBalanced(array $data, $page): void
    {
        $lines = collect($data['lines'] ?? []);
        $d = round($lines->sum(fn ($l) => (float) ($l['debit'] ?? 0)), 2);
        $c = round($lines->sum(fn ($l) => (float) ($l['credit'] ?? 0)), 2);
        if (abs($d - $c) >= 0.01 || $d <= 0) {
            Notification::make()->title('القيد غير متوازن')->body("المدين $d ≠ الدائن $c")->danger()->send();
            $page->halt();
        }
    }
}
