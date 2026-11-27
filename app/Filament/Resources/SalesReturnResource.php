<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesReturnResource\Pages;
use App\Filament\Support\DocumentResource;
use App\Models\SalesReturn;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SalesReturnResource extends DocumentResource
{
    protected static ?string $model = SalesReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static string|UnitEnum|null $navigationGroup = 'المبيعات';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'مرتجع مبيعات';

    protected static ?string $pluralModelLabel = 'مرتجعات المبيعات';

    protected static array $roles = ['sales', 'manager', 'accountant'];

    protected static string $partyType = 'customer';

    protected static string $priceSource = 'sale';

    protected static bool $payable = false;

    protected static bool $postable = true;

    protected static ?string $printKey = 'sales-return';

    protected static function extraHeaderFields(): array
    {
        return [
            Select::make('sales_invoice_id')->label(__('الفاتورة الأصلية'))->relationship('salesInvoice', 'number', fn ($query) => $query->whereNotNull('posted_at'))->searchable()->preload(),
            Select::make('treasury_id')->label(__('رد المبلغ نقداً من'))->relationship('treasury', 'name')->placeholder(__('خصم من رصيد العميل'))->helperText(__('اتركه فارغاً لخصم القيمة من حساب العميل')),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesReturns::route('/'),
            'create' => Pages\CreateSalesReturn::route('/create'),
            'view' => Pages\ViewSalesReturn::route('/{record}'),
            'edit' => Pages\EditSalesReturn::route('/{record}/edit'),
        ];
    }
}
