<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'المالية';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'دفعة';

    protected static ?string $pluralModelLabel = 'المدفوعات والإيرادات';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->columnSpanFull()->schema([
                Select::make('member_id')->label('المشترك')->relationship('member', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($r) => "{$r->name} ({$r->code})")
                    ->searchable(['name', 'code', 'phone'])->live(),
                Select::make('subscription_id')->label('الاشتراك')
                    ->relationship('subscription', 'id', fn (Builder $query, Get $get) => $query->where('member_id', $get('member_id')))
                    ->getOptionLabelFromRecordUsing(fn ($r) => $r->plan?->name.' ('.$r->start_date->format('Y-m-d').') متبقي '.$r->remaining_amount)
                    ->visible(fn (Get $get) => filled($get('member_id'))),
                TextInput::make('amount')->label('المبلغ')->numeric()->required()->minValue(0.01)->suffix(Setting::currency()),
                Select::make('method')->label('طريقة الدفع')->options(Payment::METHODS)->default('cash')->required(),
                Select::make('type')->label('النوع')->options(Payment::TYPES)->default('subscription')->required(),
                DateTimePicker::make('paid_at')->label('التاريخ')->default(now())->required(),
                TextInput::make('notes')->label('ملاحظات')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('receipt_number')->label('الإيصال')->searchable(),
                TextColumn::make('paid_at')->label('التاريخ')->dateTime('Y-m-d h:i A')->sortable(),
                TextColumn::make('member.name')->label('المشترك')->searchable()->placeholder('-'),
                TextColumn::make('amount')->label('المبلغ')->numeric(2, locale: 'en')->sortable()
                    ->summarize(Sum::make()->label('الإجمالي')->numeric(2, locale: 'en')),
                TextColumn::make('method')->label('الطريقة')->badge()->formatStateUsing(fn ($state) => Payment::METHODS[$state] ?? $state),
                TextColumn::make('type')->label('النوع')->formatStateUsing(fn ($state) => Payment::TYPES[$state] ?? $state),
                TextColumn::make('subscription.plan.name')->label('الباقة')->placeholder('-'),
                TextColumn::make('receiver.name')->label('استلمها'),
                TextColumn::make('notes')->label('ملاحظات')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('method')->label('طريقة الدفع')->options(Payment::METHODS),
                SelectFilter::make('type')->label('النوع')->options(Payment::TYPES),
                SelectFilter::make('received_by')->label('الموظف')->relationship('receiver', 'name'),
                Filter::make('date')->schema([
                    DatePicker::make('from')->label('من')->default(today()->startOfMonth()->toDateString()),
                    DatePicker::make('until')->label('إلى'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('paid_at', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('paid_at', '<=', $d))),
            ])
            ->recordActions([
                Action::make('print')->label('إيصال')->icon(Heroicon::OutlinedPrinter)->color('gray')
                    ->url(fn (Payment $r) => route('print.receipt', $r), true),
                EditAction::make()->visible(fn () => auth()->user()->isManager()),
                DeleteAction::make()->visible(fn () => auth()->user()->isManager()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePayments::route('/')];
    }
}
