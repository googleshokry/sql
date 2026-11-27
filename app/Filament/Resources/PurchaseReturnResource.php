<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseReturnResource\Pages;
use App\Filament\Support\DocumentResource;
use App\Models\PurchaseReturn;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class PurchaseReturnResource extends DocumentResource
{
    protected static ?string $model = PurchaseReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static string|UnitEnum|null $navigationGroup = 'المشتريات';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'مرتجع مشتريات';

    protected static ?string $pluralModelLabel = 'مرتجعات المشتريات';

    protected static array $roles = ['purchases', 'manager', 'accountant'];

    protected static string $partyType = 'supplier';

    protected static string $priceSource = 'purchase';

    protected static bool $payable = false;

    protected static bool $postable = true;

    protected static ?string $printKey = 'purchase-return';

    protected static function extraHeaderFields(): array
    {
        return [
            Select::make('purchase_invoice_id')->label(__('الفاتورة الأصلية'))->relationship('purchaseInvoice', 'number', fn ($query) => $query->whereNotNull('posted_at'))->searchable()->preload(),
            Select::make('treasury_id')->label(__('استرداد نقدي إلى'))->relationship('treasury', 'name')->placeholder(__('خصم من رصيد المورد')),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseReturns::route('/'),
            'create' => Pages\CreatePurchaseReturn::route('/create'),
            'view' => Pages\ViewPurchaseReturn::route('/{record}'),
            'edit' => Pages\EditPurchaseReturn::route('/{record}/edit'),
        ];
    }
}
