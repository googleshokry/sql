<?php

namespace App\Filament\Platform\Resources\Payments\Pages;

use App\Filament\Platform\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePayments extends ManageRecords
{
    protected static string $resource = PaymentResource::class;
}
