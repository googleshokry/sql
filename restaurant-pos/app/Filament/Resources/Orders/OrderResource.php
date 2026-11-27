<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'المبيعات';

    protected static ?string $modelLabel = 'طلب';

    protected static ?string $pluralModelLabel = 'الطلبات';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = Order::whereIn('status', OrderStatus::open())->count();

        return $count ? (string) $count : null;
    }

    public static function canCreate(): bool
    {
        return false; // الطلبات تنشأ من شاشة الكاشير
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->schema([
                TextEntry::make('number')->label('رقم الطلب'),
                TextEntry::make('type')->label('النوع')->badge(),
                TextEntry::make('status')->label('الحالة')->badge(),
                TextEntry::make('created_at')->label('التاريخ')->dateTime('Y-m-d h:i A'),
                TextEntry::make('restaurantTable.name')->label('الطاولة')->placeholder('-'),
                TextEntry::make('customer.name')->label('العميل')->placeholder('-'),
                TextEntry::make('customer.phone')->label('الموبايل')->placeholder('-'),
                TextEntry::make('driver.name')->label('الطيار')->placeholder('-'),
                TextEntry::make('delivery_address')->label('العنوان')->placeholder('-')->columnSpanFull(),
            ]),
            RepeatableEntry::make('items')->label('الأصناف')->columnSpanFull()->table([
                \Filament\Infolists\Components\RepeatableEntry\TableColumn::make('الصنف'),
                \Filament\Infolists\Components\RepeatableEntry\TableColumn::make('الكمية'),
                \Filament\Infolists\Components\RepeatableEntry\TableColumn::make('السعر'),
                \Filament\Infolists\Components\RepeatableEntry\TableColumn::make('الإجمالي'),
            ])->schema([
                TextEntry::make('product_name'),
                TextEntry::make('quantity'),
                TextEntry::make('price')->numeric(2),
                TextEntry::make('total')->numeric(2),
            ]),
            Grid::make(6)->schema([
                TextEntry::make('subtotal')->label('المجموع')->numeric(2),
                TextEntry::make('discount')->label('الخصم')->numeric(2),
                TextEntry::make('tax')->label('الضريبة + الخدمة')->state(fn (Order $r) => number_format($r->tax + $r->service, 2)),
                TextEntry::make('delivery_fee')->label('التوصيل')->numeric(2),
                TextEntry::make('total')->label('الإجمالي')->numeric(2)->weight('bold'),
                TextEntry::make('payment_method')->label('الدفع')->placeholder('غير مدفوع'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->poll('15s')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['restaurantTable', 'customer', 'driver', 'user']))
            ->columns([
                TextColumn::make('number')->label('رقم')->searchable()->weight('bold'),
                TextColumn::make('type')->label('النوع')->badge(),
                TextColumn::make('status')->label('الحالة')->badge(),
                TextColumn::make('restaurantTable.name')->label('طاولة')->placeholder('-'),
                TextColumn::make('customer.name')->label('العميل')->placeholder('-')
                    ->description(fn (Order $r) => $r->customer?->phone)
                    ->searchable(),
                TextColumn::make('driver.name')->label('الطيار')->placeholder('-')->toggleable(),
                TextColumn::make('total')->label('الإجمالي')->numeric(2)->sortable()
                    ->summarize(Sum::make()->label('المجموع')->numeric(2)),
                IconColumn::make('is_paid')->label('مدفوع')->boolean(),
                TextColumn::make('payment_method')->label('الدفع')->toggleable(),
                TextColumn::make('user.name')->label('الكاشير')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('الوقت')->dateTime('d/m h:i A')->sortable()
                    ->description(fn (Order $r) => $r->created_at->diffForHumans()),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options(OrderStatus::class)->multiple(),
                SelectFilter::make('type')->label('النوع')->options(OrderType::class),
                SelectFilter::make('payment_method')->label('طريقة الدفع')->options(PaymentMethod::class),
                SelectFilter::make('driver_id')->label('الطيار')->relationship('driver', 'name'),
                SelectFilter::make('user_id')->label('الكاشير')->relationship('user', 'name'),
                Filter::make('date')->label('الفترة')
                    ->schema([
                        DatePicker::make('from')->label('من'),
                        DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                Action::make('pay')->label('تحصيل')->icon(Heroicon::OutlinedBanknotes)->color('success')
                    ->visible(fn (Order $r) => ! $r->is_paid && $r->status !== OrderStatus::Cancelled)
                    ->schema([
                        Select::make('payment_method')->label('طريقة الدفع')
                            ->options(PaymentMethod::class)->default(PaymentMethod::Cash)->required(),
                        TextInput::make('paid_amount')->label('المبلغ المدفوع')->numeric()
                            ->default(fn (Order $r) => $r->total)->required(),
                    ])
                    ->action(function (Order $r, array $data) {
                        if ((float) $data['paid_amount'] < (float) $r->total) {
                            Notification::make()->danger()->title('المبلغ المدفوع أقل من الإجمالي')->send();

                            return;
                        }
                        $method = $data['payment_method'] instanceof PaymentMethod
                            ? $data['payment_method'] : PaymentMethod::from($data['payment_method']);
                        $r->markPaid($method, (float) $data['paid_amount']);
                        Notification::make()->success()
                            ->title('تم التحصيل - الباقي: '.number_format($r->change_amount, 2))->send();
                    }),
                ActionGroup::make([
                    ViewAction::make()->label('عرض'),
                    Action::make('receipt')->label('طباعة الفاتورة')->icon(Heroicon::OutlinedPrinter)
                        ->url(fn (Order $r) => route('orders.receipt', $r))->openUrlInNewTab(),
                    Action::make('kitchen')->label('طباعة للمطبخ')->icon(Heroicon::OutlinedFire)
                        ->url(fn (Order $r) => route('orders.kitchen', $r))->openUrlInNewTab(),
                    Action::make('status')->label('تغيير الحالة')->icon(Heroicon::OutlinedArrowPath)
                        ->visible(fn (Order $r) => $r->status !== OrderStatus::Cancelled)
                        ->fillForm(fn (Order $r) => ['status' => $r->status])
                        ->schema([
                            Select::make('status')->label('الحالة')->options(OrderStatus::class)->required(),
                        ])
                        ->action(fn (Order $r, array $data) => $r->update(['status' => $data['status']])),
                    Action::make('driver')->label('تعيين طيار')->icon(Heroicon::OutlinedTruck)
                        ->visible(fn (Order $r) => $r->type === OrderType::Delivery)
                        ->fillForm(fn (Order $r) => ['driver_id' => $r->driver_id])
                        ->schema([
                            Select::make('driver_id')->label('الطيار')
                                ->relationship('driver', 'name', fn (Builder $query) => $query->where('is_active', true))
                                ->required(),
                        ])
                        ->action(fn (Order $r, array $data) => $r->update([
                            'driver_id' => $data['driver_id'],
                            'status' => OrderStatus::OutForDelivery,
                        ])),
                    Action::make('cancel')->label('إلغاء الطلب')->icon(Heroicon::OutlinedXCircle)->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Order $r) => $r->status !== OrderStatus::Cancelled && auth()->user()->isAdmin())
                        ->action(fn (Order $r) => $r->update(['status' => OrderStatus::Cancelled])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageOrders::route('/')];
    }
}
