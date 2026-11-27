<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Concerns\TranslatesPageLabels;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

abstract class ReportPage extends Page
{
    use HasRoleAccess;
    use TranslatesPageLabels;

    protected static string|UnitEnum|null $navigationGroup = 'التقارير';

    protected static array $roles = ['accountant', 'manager'];

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    public function mount(): void
    {
        $this->from ??= now()->startOfYear()->toDateString();
        $this->to ??= now()->toDateString();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')->label(__('طباعة'))->icon(Heroicon::OutlinedPrinter)->color('gray')
                ->alpineClickHandler('window.print()'),
        ];
    }

    protected function getViewData(): array
    {
        return ['data' => $this->data()];
    }

    abstract protected function data(): array;
}
