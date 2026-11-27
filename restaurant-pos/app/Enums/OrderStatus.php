<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel, HasColor
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case OutForDelivery = 'out_for_delivery';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'جديد',
            self::Preparing => 'قيد التحضير',
            self::Ready => 'جاهز',
            self::OutForDelivery => 'مع الطيار',
            self::Completed => 'مكتمل',
            self::Cancelled => 'ملغي',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Preparing => 'warning',
            self::Ready => 'info',
            self::OutForDelivery => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** الحالات التي يعتبر فيها الطلب مفتوحاً */
    public static function open(): array
    {
        return [self::Pending, self::Preparing, self::Ready, self::OutForDelivery];
    }
}
