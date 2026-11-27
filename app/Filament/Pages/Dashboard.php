<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    use TranslatesPageLabels;

    protected static ?string $title = 'لوحة التحكم';

    public function getColumns(): int|array
    {
        return 2;
    }
}
