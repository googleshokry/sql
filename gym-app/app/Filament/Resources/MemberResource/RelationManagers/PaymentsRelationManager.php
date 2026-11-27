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

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('المدفوعات');
    }

    protected static function getModelLabel(): ?string
    {
        return __('دفعة');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('subscription_id')->label(__('الاشتراك'))
                ->options(fn () => $this->getOwnerRecord()->subscriptions()->with('plan')->get()
                    ->mapWithKeys(fn ($s) => [$s->id => $s->plan->name.' ('.$s->start_date->format('Y-m-d').')'])),
            TextInput::make('amount')->label(__('المبلغ'))->numeric()->required()->minValue(0.01)->suffix(Setting::currency()),
            Select::make('method')->label(__('الطريقة'))->options(Payment::methods())->default('cash')->required(),
            Select::make('type')->label(__('النوع'))->options(Payment::types())->default('subscription')->required(),
            DateTimePicker::make('paid_at')->label(__('التاريخ'))->default(now())->required(),
            TextInput::make('notes')->label(__('ملاحظات')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receipt_number')->label(__('رقم الإيصال')),
                TextColumn::make('paid_at')->label(__('التاريخ'))->dateTime('Y-m-d h:i A'),
                TextColumn::make('amount')->label(__('المبلغ'))->numeric(2, locale: 'en')->summarize(Sum::make()->label(__('الإجمالي'))->numeric(2, locale: 'en')),
                TextColumn::make('method')->label(__('الطريقة'))->formatStateUsing(fn ($state) => Payment::methods()[$state] ?? $state),
                TextColumn::make('type')->label(__('النوع'))->badge()->formatStateUsing(fn ($state) => Payment::types()[$state] ?? $state),
                TextColumn::make('subscription.plan.name')->label(__('الاشتراك'))->placeholder('-'),
                TextColumn::make('receiver.name')->label(__('استلمها')),
            ])
            ->headerActions([CreateAction::make()->label(__('تسجيل دفعة'))])
            ->recordActions([
                Action::make('print')->label(__('إيصال'))->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (Payment $r) => route('print.receipt', $r), true),
            ]);
    }
}
