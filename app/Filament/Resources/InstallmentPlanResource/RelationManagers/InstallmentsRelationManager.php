<?php

namespace App\Filament\Resources\InstallmentPlanResource\RelationManagers;

use App\Filament\Actions\DocumentActions;
use App\Models\Installment;
use App\Models\Treasury;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    protected static ?string $title = 'جدول الأقساط';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sequence')->label('#'),
                TextColumn::make('due_date')->label('تاريخ الاستحقاق')->date('Y-m-d')
                    ->color(fn (Installment $record) => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('amount')->label('قيمة القسط')->numeric(2)->summarize(Sum::make()->label('')),
                TextColumn::make('paid_amount')->label('المدفوع')->numeric(2)->summarize(Sum::make()->label('')),
                TextColumn::make('paid_at')->label('تاريخ السداد')->date('Y-m-d')->placeholder('-'),
                TextColumn::make('state')->label('الحالة')->badge()
                    ->state(fn (Installment $record) => $record->isPaid() ? 'مسدد' : ($record->isOverdue() ? 'متأخر' : 'مستحق'))
                    ->color(fn ($state) => ['مسدد' => 'success', 'متأخر' => 'danger', 'مستحق' => 'warning'][$state]),
            ])
            ->recordActions([
                Action::make('collect')->label('تحصيل')->icon(Heroicon::OutlinedBanknotes)->color('success')
                    ->visible(fn (Installment $record) => ! $record->isPaid() && $this->getOwnerRecord()->status !== 'cancelled')
                    ->fillForm(fn (Installment $record) => [
                        'amount' => (float) $record->amount - (float) $record->paid_amount,
                        'treasury_id' => Treasury::query()->where('is_default', true)->value('id'),
                        'date' => now()->toDateString(),
                    ])
                    ->schema([
                        TextInput::make('amount')->label('المبلغ')->numeric()->required(),
                        Select::make('treasury_id')->label('الخزينة')->options(Treasury::query()->pluck('name', 'id'))->required(),
                        DatePicker::make('date')->label('التاريخ')->required(),
                    ])
                    ->action(fn (Installment $record, array $data) => DocumentActions::run(
                        fn () => $record->collect((int) $data['treasury_id'], (float) $data['amount'], $data['date']),
                        'تم تحصيل القسط وإنشاء سند القبض',
                    )),
                Action::make('receipt')->label('إيصال')->icon(Heroicon::OutlinedPrinter)->color('gray')
                    ->visible(fn (Installment $record) => $record->payment_id)
                    ->url(fn (Installment $record) => route('print.document', ['payment', $record->payment_id]))->openUrlInNewTab(),
            ])
            ->paginated(false);
    }
}
