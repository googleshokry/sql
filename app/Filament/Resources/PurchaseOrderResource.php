<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Filament\Support\DocumentResource;
use App\Models\PurchaseOrder;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class PurchaseOrderResource extends DocumentResource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'المشتريات';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'أمر شراء';

    protected static ?string $pluralModelLabel = 'أوامر الشراء';

    protected static array $roles = ['purchases', 'manager'];

    protected static string $partyType = 'supplier';

    protected static string $priceSource = 'purchase';

    protected static bool $payable = false;

    protected static bool $postable = false;

    protected static ?string $printKey = 'purchase-order';

    protected static function extraHeaderFields(): array
    {
        return [
            DatePicker::make('valid_until')->label('تاريخ التوريد المتوقع'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'view' => Pages\ViewPurchaseOrder::route('/{record}'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
