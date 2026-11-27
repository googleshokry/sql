<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseInvoiceResource\Pages;
use App\Filament\Support\DocumentResource;
use App\Models\PurchaseInvoice;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class PurchaseInvoiceResource extends DocumentResource
{
    protected static ?string $model = PurchaseInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'المشتريات';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'فاتورة مشتريات';

    protected static ?string $pluralModelLabel = 'فواتير المشتريات';

    protected static array $roles = ['purchases', 'manager', 'accountant'];

    protected static string $partyType = 'supplier';

    protected static string $priceSource = 'purchase';

    protected static bool $payable = true;

    protected static bool $postable = true;

    protected static ?string $printKey = 'purchase-invoice';

    protected static function extraHeaderFields(): array
    {
        return [
            DatePicker::make('due_date')->label('تاريخ الاستحقاق'),
            TextInput::make('reference')->label('رقم فاتورة المورد'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseInvoices::route('/'),
            'create' => Pages\CreatePurchaseInvoice::route('/create'),
            'view' => Pages\ViewPurchaseInvoice::route('/{record}'),
            'edit' => Pages\EditPurchaseInvoice::route('/{record}/edit'),
        ];
    }
}
