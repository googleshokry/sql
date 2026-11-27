<?php

namespace App\Filament\Super\Resources;

use App\Filament\Super\Resources\GymInvoiceResource\Pages;
use App\Models\Gym;
use App\Models\GymInvoice;
use App\Models\SaasPlan;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GymInvoiceResource extends Resource
{
    protected static ?string $model = GymInvoice::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('فاتورة');
    }

    public static function getPluralModelLabel(): string
    {
        return __('فواتير الجيمات');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('gym_id')->label(__('الجيم'))->options(Gym::pluck('name', 'id'))->searchable()->required(),
            Select::make('saas_plan_id')->label(__('الباقة'))->options(SaasPlan::pluck('name', 'id'))->required(),
            Select::make('billing_cycle')->label(__('دورة الفوترة'))->options(['monthly' => __('شهري'), 'yearly' => __('سنوي')])->default('monthly')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label(__('رقم الفاتورة'))->searchable(),
                TextColumn::make('gym.name')->label(__('الجيم'))->searchable(),
                TextColumn::make('saasPlan.name')->label(__('الباقة')),
                TextColumn::make('period')->label(__('الفترة'))
                    ->state(fn (GymInvoice $r) => $r->period_start->format('Y-m-d').' ← '.$r->period_end->format('Y-m-d')),
                TextColumn::make('amount')->label(__('المبلغ'))->numeric(2, locale: 'en')
                    ->summarize(Sum::make()->label(__('الإجمالي'))->numeric(2, locale: 'en')),
                TextColumn::make('status')->label(__('الحالة'))->badge()
                    ->formatStateUsing(fn ($state) => GymInvoice::statuses()[$state] ?? $state)
                    ->color(fn ($state) => ['paid' => 'success', 'unpaid' => 'warning', 'void' => 'gray'][$state] ?? 'gray'),
                TextColumn::make('paid_at')->label(__('تاريخ الدفع'))->dateTime('Y-m-d')->placeholder('-'),
                TextColumn::make('reference')->label(__('رقم العملية'))->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([SelectFilter::make('status')->label(__('الحالة'))->options(GymInvoice::statuses())])
            ->recordActions([
                Action::make('markPaid')->label(__('تأكيد الدفع'))->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (GymInvoice $r) => $r->status === 'unpaid')
                    ->schema([
                        TextInput::make('method')->label(__('طريقة الدفع'))->default('InstaPay'),
                        TextInput::make('reference')->label(__('رقم العملية')),
                    ])
                    ->action(fn (GymInvoice $r, array $data) => $r->markPaid($data['method'], $data['reference'])),
                Action::make('void')->label(__('إلغاء'))->color('gray')->requiresConfirmation()
                    ->visible(fn (GymInvoice $r) => $r->status === 'unpaid')
                    ->action(fn (GymInvoice $r) => $r->update(['status' => 'void'])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageGymInvoices::route('/')];
    }
}
