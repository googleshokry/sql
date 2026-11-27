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
use Illuminate\Database\Eloquent\Model;

class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    protected static ?string $title = 'جدول الأقساط';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('جدول الأقساط');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sequence')->label('#'),
                TextColumn::make('due_date')->label(__('تاريخ الاستحقاق'))->date('Y-m-d')
                    ->color(fn (Installment $record) => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('amount')->label(__('قيمة القسط'))->numeric(2)->summarize(Sum::make()->label('')),
                TextColumn::make('paid_amount')->label(__('المدفوع'))->numeric(2)->summarize(Sum::make()->label('')),
                TextColumn::make('paid_at')->label(__('تاريخ السداد'))->date('Y-m-d')->placeholder('-'),
                TextColumn::make('state')->label(__('الحالة'))->badge()
                    ->state(fn (Installment $record) => $record->isPaid() ? __('مسدد') : ($record->isOverdue() ? __('متأخر') : __('مستحق')))
                    ->color(fn ($state) => [__('مسدد') => 'success', __('متأخر') => 'danger', __('مستحق') => 'warning'][$state]),
            ])
            ->recordActions([
                Action::make('collect')->label(__('تحصيل'))->icon(Heroicon::OutlinedBanknotes)->color('success')
                    ->visible(fn (Installment $record) => ! $record->isPaid() && $this->getOwnerRecord()->status !== 'cancelled')
                    ->fillForm(fn (Installment $record) => [
                        'amount' => (float) $record->amount - (float) $record->paid_amount,
                        'treasury_id' => Treasury::query()->where('is_default', true)->value('id'),
                        'date' => now()->toDateString(),
                    ])
                    ->schema([
                        TextInput::make('amount')->label(__('المبلغ'))->numeric()->required(),
                        Select::make('treasury_id')->label(__('الخزينة'))->options(Treasury::query()->pluck('name', 'id'))->required(),
                        DatePicker::make('date')->label(__('التاريخ'))->required(),
                    ])
                    ->action(fn (Installment $record, array $data) => DocumentActions::run(
                        fn () => $record->collect((int) $data['treasury_id'], (float) $data['amount'], $data['date']),
                        __('تم تحصيل القسط وإنشاء سند القبض'),
                    )),
                Action::make('receipt')->label(__('إيصال'))->icon(Heroicon::OutlinedPrinter)->color('gray')
                    ->visible(fn (Installment $record) => $record->payment_id)
                    ->url(fn (Installment $record) => route('print.document', ['payment', $record->payment_id]))->openUrlInNewTab(),
            ])
            ->paginated(false);
    }
}
