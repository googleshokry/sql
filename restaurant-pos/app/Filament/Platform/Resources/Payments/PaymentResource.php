<?php

namespace App\Filament\Platform\Resources\Payments;

use App\Filament\Platform\Resources\Payments\Pages\ManagePayments;
use App\Models\Central\Payment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('دفعة');
    }

    public static function getPluralModelLabel(): string
    {
        return __('المدفوعات');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Payment::where('status', 'pending')->where('method', '!=', 'paymob')->count();

        return $n ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        $statusLabels = fn () => ['pending' => __('قيد المراجعة'), 'paid' => __('مدفوع'), 'failed' => __('فشل')];

        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['tenant', 'plan']))
            ->columns([
                TextColumn::make('created_at')->label(__('التاريخ'))->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('tenant.name')->label(__('المطعم'))->description(fn (Payment $r) => $r->tenant?->slug)->searchable(),
                TextColumn::make('plan.name')->label(__('الباقة')),
                TextColumn::make('months')->label(__('شهور')),
                TextColumn::make('amount')->label(__('المبلغ'))->numeric(2)
                    ->summarize(Sum::make()->label(__('الإجمالي'))->numeric(2)),
                TextColumn::make('method')->label(__('طريقة الدفع'))->formatStateUsing(fn ($state) => Payment::methodLabel($state)),
                TextColumn::make('reference')->label(__('المرجع'))->toggleable(),
                TextColumn::make('status')->label(__('الحالة'))->badge()
                    ->formatStateUsing(fn ($state) => $statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => ['pending' => 'warning', 'paid' => 'success', 'failed' => 'danger'][$state] ?? 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('الحالة'))->options($statusLabels),
                SelectFilter::make('method')->label(__('طريقة الدفع'))
                    ->options(fn () => collect(Payment::METHODS)->mapWithKeys(fn ($m) => [$m => Payment::methodLabel($m)])),
            ])
            ->recordActions([
                Action::make('approve')->label(__('تأكيد وتفعيل'))->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (Payment $r) => $r->status === 'pending')->requiresConfirmation()
                    ->action(function (Payment $r) {
                        $r->markPaid();
                        Notification::make()->success()->title(__('تم تفعيل اشتراك :name', ['name' => $r->tenant->name]))->send();
                    }),
                Action::make('reject')->label(__('رفض'))->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->visible(fn (Payment $r) => $r->status === 'pending')->requiresConfirmation()
                    ->action(fn (Payment $r) => $r->update(['status' => 'failed'])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePayments::route('/')];
    }
}
