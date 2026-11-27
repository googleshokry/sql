<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesInvoiceResource\Pages;
use App\Filament\Support\DocumentResource;
use App\Models\SalesInvoice;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SalesInvoiceResource extends DocumentResource
{
    protected static ?string $model = SalesInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'المبيعات';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'فاتورة مبيعات';

    protected static ?string $pluralModelLabel = 'فواتير المبيعات';

    protected static array $roles = ['sales', 'manager', 'accountant'];

    protected static string $partyType = 'customer';

    protected static string $priceSource = 'sale';

    protected static bool $payable = true;

    protected static bool $postable = true;

    protected static ?string $printKey = 'sales-invoice';

    protected static function extraHeaderFields(): array
    {
        return [
            DatePicker::make('due_date')->label(__('تاريخ الاستحقاق')),
            TextInput::make('reference')->label(__('مرجع / رقم أمر الشراء')),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesInvoices::route('/'),
            'create' => Pages\CreateSalesInvoice::route('/create'),
            'view' => Pages\ViewSalesInvoice::route('/{record}'),
            'edit' => Pages\EditSalesInvoice::route('/{record}/edit'),
        ];
    }
}
