<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuotationResource\Pages;
use App\Filament\Support\DocumentResource;
use App\Models\Quotation;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class QuotationResource extends DocumentResource
{
    protected static ?string $model = Quotation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'المبيعات';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'عرض سعر';

    protected static ?string $pluralModelLabel = 'عروض الأسعار';

    protected static array $roles = ['sales', 'manager'];

    protected static string $partyType = 'customer';

    protected static string $priceSource = 'sale';

    protected static bool $payable = false;

    protected static bool $postable = false;

    protected static ?string $printKey = 'quotation';

    protected static function extraHeaderFields(): array
    {
        return [
            DatePicker::make('valid_until')->label('صالح حتى')->default(now()->addDays(15)),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuotations::route('/'),
            'create' => Pages\CreateQuotation::route('/create'),
            'view' => Pages\ViewQuotation::route('/{record}'),
            'edit' => Pages\EditQuotation::route('/{record}/edit'),
        ];
    }
}
