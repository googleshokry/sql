<?php

namespace App\Filament\Resources\MemberResource\RelationManagers;

use App\Models\Payment;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'المدفوعات';

    protected static ?string $modelLabel = 'دفعة';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('subscription_id')->label('الاشتراك')
                ->options(fn () => $this->getOwnerRecord()->subscriptions()->with('plan')->get()
                    ->mapWithKeys(fn ($s) => [$s->id => $s->plan->name.' ('.$s->start_date->format('Y-m-d').')'])),
            TextInput::make('amount')->label('المبلغ')->numeric()->required()->minValue(0.01)->suffix(Setting::currency()),
            Select::make('method')->label('الطريقة')->options(Payment::METHODS)->default('cash')->required(),
            Select::make('type')->label('النوع')->options(Payment::TYPES)->default('subscription')->required(),
            DateTimePicker::make('paid_at')->label('التاريخ')->default(now())->required(),
            TextInput::make('notes')->label('ملاحظات'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receipt_number')->label('رقم الإيصال'),
                TextColumn::make('paid_at')->label('التاريخ')->dateTime('Y-m-d h:i A'),
                TextColumn::make('amount')->label('المبلغ')->numeric(2, locale: 'en')->summarize(Sum::make()->label('الإجمالي')->numeric(2, locale: 'en')),
                TextColumn::make('method')->label('الطريقة')->formatStateUsing(fn ($state) => Payment::METHODS[$state] ?? $state),
                TextColumn::make('type')->label('النوع')->badge()->formatStateUsing(fn ($state) => Payment::TYPES[$state] ?? $state),
                TextColumn::make('subscription.plan.name')->label('الاشتراك')->placeholder('-'),
                TextColumn::make('receiver.name')->label('استلمها'),
            ])
            ->headerActions([CreateAction::make()->label('تسجيل دفعة')])
            ->recordActions([
                Action::make('print')->label('إيصال')->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (Payment $r) => route('print.receipt', $r), true),
            ]);
    }
}
