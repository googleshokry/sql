<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use Filament\Widgets\ChartWidget;

class AttendanceChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'الحضور اليومي (آخر 14 يوم)';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $days = collect(range(13, 0))->map(fn ($i) => today()->subDays($i));

        return [
            'datasets' => [[
                'label' => 'عدد الحضور',
                'data' => $days->map(fn ($d) => Attendance::whereDate('check_in_at', $d)->count())->all(),
                'borderColor' => '#f59e0b',
                'backgroundColor' => 'rgba(245,158,11,.2)',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $days->map(fn ($d) => $d->format('m-d'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
